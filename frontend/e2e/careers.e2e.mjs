// careers.paxofi.com (D-018, D-019): the same build run with SITE_SECTION=careers.
// A small local HTTP server stands in for the API that the website server reads
// (open roles); the browser's CV upload and application calls are intercepted.
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const PORT = Number(process.env.E2E_PORT || 3123) + 6;
const API_PORT = PORT + 1;
const API = `http://127.0.0.1:${API_PORT}/api/v1`;

const ROLE = {
  slug: "software-engineer-fellow", code: "SE", title: "Software Engineer Fellow", family: "Technology and engineering",
  summary: "Design, build and test Paxofi software.", purpose: "Take part in the design, development and testing of Paxofi software systems.",
  responsibilities: ["Contribute to backend APIs", "Write tests"], deliverables: ["Working, tested features"], competencies: ["PHP", "SQL"], tools: ["PHP 8+", "Git / GitHub"],
  evidence: "A code sample you can walk us through.", assessment: "Practical coding task.", interview: "Technical reasoning.",
};
const ROLES = [ROLE, { ...ROLE, slug: "hr-officer-fellow", code: "HR", title: "HR Officer Fellow", family: "People and HR", summary: "Support recruitment and onboarding." }];

let api;
before(async () => {
  api = createServer((request, response) => {
    const path = new URL(request.url, "http://localhost").pathname;
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    if (path === "/api/v1/careers/roles") return json(200, { data: ROLES });
    const one = path.match(/^\/api\/v1\/careers\/roles\/([a-z-]+)$/);
    if (one) {
      const role = ROLES.find((r) => r.slug === one[1]);
      return role ? json(200, { data: role }) : json(404, { success: false, error: { code: "NOT_FOUND", message: "This role is not open for applications." } });
    }
    return json(404, { success: false, error: { code: "NOT_FOUND", message: "Not found." } });
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(PORT, { apiBase: API, host: "0.0.0.0", env: { SITE_SECTION: "careers", CAREERS_SITE_URL: "https://careers.paxofi.com" } });

/** Answers the browser's CV upload and application calls, including CORS preflights. */
async function mockApplyApi(page, { apply }) {
  const seen = { upload: null, apply: null };
  const cors = { "Access-Control-Allow-Origin": BASE, "Access-Control-Allow-Methods": "POST, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept" };
  await page.route(`${API}/careers/cv?*`, async (route) => {
    if (route.request().method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
    seen.upload = { url: route.request().url(), type: route.request().headers()["content-type"], size: route.request().postDataBuffer()?.length };
    return route.fulfill({ status: 201, headers: cors, contentType: "application/json", body: JSON.stringify({ data: { token: "t".repeat(43), filename: "cv.pdf", size_bytes: 20, format: "PDF" } }) });
  });
  await page.route(`${API}/careers/roles/*/apply`, async (route) => {
    if (route.request().method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
    seen.apply = route.request().postDataJSON();
    const { status, body } = apply(seen.apply);
    return route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(body) });
  });
  return seen;
}

async function fillApplication(page) {
  await page.getByLabel("Full name").fill("Ada Lovelace");
  await page.getByLabel("Email").fill("ada@example.com");
  await page.getByLabel("Country and city").fill("Lagos, Nigeria");
  await page.getByLabel("Why do you want this role?").fill("I want to build real products with a delivery-focused team and learn from mentors.");
  await page.getByLabel("Your relevant experience or evidence").fill("I built a payments dashboard in React and a PHP API for it, with tests and CI.");
  await page.getByLabel("I am 18 or older.").check();
  await page.getByLabel(/I have read the applicant privacy notice/).check();
}

describe("careers site", () => {
  test("the home page lists the open roles, with the fellowship terms, accessibly", async () => {
    const page = await newPage();
    const response = await page.goto(BASE + "/");
    assert.equal(response.status(), 200);
    assert.match(await page.title(), /Careers at Paxofi/);
    assert.equal(await page.locator('link[rel="canonical"]').getAttribute("href"), "https://careers.paxofi.com");
    await page.getByRole("link", { name: "Software Engineer Fellow" }).waitFor();
    assert.equal(await page.locator(".role-card").count(), 2);
    assert.ok(await page.getByText("Is the fellowship paid?").isVisible(), "FAQ from the Careers page text");
    assert.ok(await page.locator('a[href="https://corporate.paxofi.com"], a[href^="http"][href*="paxofi"]').first().isVisible(), "link back to the corporate site");
    await assertAccessible(page, "careers home");
    await page.context().close();
  });

  test("the staff area, internal paths and unknown roles are not served here", async () => {
    for (const path of ["/admin/login", "/careers-site", "/roles/no-such-role"]) {
      assert.equal((await fetch(BASE + path)).status, 404, path);
    }
    const robots = await (await fetch(`${BASE}/robots.txt`)).text();
    assert.match(robots, /Sitemap: https:\/\/careers\.paxofi\.com\/sitemap\.xml/);
    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    assert.match(sitemap, /https:\/\/careers\.paxofi\.com\/roles\/software-engineer-fellow/);
    assert.match(sitemap, /https:\/\/careers\.paxofi\.com\/privacy/);
    assert.equal((await fetch(`${BASE}/privacy`)).status, 200);
  });

  test("a candidate applies with a CV and gets a reference", async () => {
    const page = await newPage();
    const seen = await mockApplyApi(page, { apply: () => ({ status: 201, body: { data: { reference: "PIF-7KQ3MZ", role: ROLE.title } } }) });
    await page.goto(`${BASE}/roles/${ROLE.slug}`);
    assert.equal(await page.locator("h1").textContent(), ROLE.title);
    assert.ok(await page.getByText("Unpaid fellowship: no stipend or salary").isVisible());
    await assertAccessible(page, "role page");

    await fillApplication(page);
    await page.getByLabel(/^CV/).setInputFiles({ name: "Ada CV.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.7\n%%EOF\n") });
    await page.getByRole("button", { name: "Submit application" }).click();
    await page.getByRole("heading", { name: "Application received" }).waitFor();
    assert.ok(await page.getByText("PIF-7KQ3MZ").isVisible());

    assert.equal(seen.upload.type, "application/octet-stream");
    assert.match(seen.upload.url, /filename=Ada\+CV\.pdf/);
    assert.equal(seen.apply.cv_token, "t".repeat(43));
    assert.equal(seen.apply.age_confirmed, true);
    assert.equal(seen.apply.privacy_consent, true);
    assert.equal(seen.apply.hours_per_week, 20);
    assert.equal(seen.apply.website, "", "honeypot left empty");
    await assertAccessible(page, "confirmation");
    await page.context().close();
  });

  test("errors from the API are shown on the fields, and a wrong file type never uploads", async () => {
    const page = await newPage();
    const seen = await mockApplyApi(page, {
      apply: () => ({ status: 422, body: { success: false, error: { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { cv: "Add your CV, a portfolio link or your LinkedIn profile, so we can see your experience." } } } } }),
    });
    await page.goto(`${BASE}/roles/${ROLE.slug}`);
    await fillApplication(page);
    await page.getByLabel(/^CV/).setInputFiles({ name: "cv.png", mimeType: "image/png", buffer: Buffer.from("png") });
    await page.getByRole("button", { name: "Submit application" }).click();
    await page.getByText("Upload your CV as a PDF or Word (.docx) file.").first().waitFor();
    assert.equal(seen.upload, null);

    await page.getByLabel(/^CV/).setInputFiles([]);
    await page.getByRole("button", { name: "Submit application" }).click();
    await page.getByText("Add your CV, a portfolio link or your LinkedIn profile").first().waitFor();
    assert.equal(await page.getByLabel(/^CV/).getAttribute("aria-invalid"), "true");
    await page.context().close();
  });

  test("fits a phone screen without sideways scrolling", async () => {
    const page = await newPage({ viewport: { width: 360, height: 740 } });
    for (const path of ["/", `/roles/${ROLE.slug}`, "/privacy"]) {
      await page.goto(BASE + path);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 0, `${path} overflows by ${overflow}px`);
    }
    await page.context().close();
  });
});
