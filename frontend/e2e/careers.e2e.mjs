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

const ENGAGEMENT = { type: "fellow", label: "Fellow", term: "24 programme weeks", hours_min: 15, hours_target: 20, for_whom: "People with foundation skills who want to own real deliverables." };
const ROLE = {
  slug: "software-engineer-fellow", code: "SE", engagement_type: "fellow", engagement: ENGAGEMENT, title: "Software Engineer Fellow", family: "Technology and engineering",
  summary: "Design, build and test Paxofi software.", purpose: "Take part in the design, development and testing of Paxofi software systems.",
  responsibilities: ["Contribute to backend APIs", "Write tests"], deliverables: ["Working, tested features"], competencies: ["PHP", "SQL"], tools: ["PHP 8+", "Git / GitHub"],
  evidence: "A code sample you can walk us through.", assessment: "Practical coding task.", interview: "Technical reasoning.",
};
const INTERN = { ...ROLE, slug: "software-engineer-intern", engagement_type: "intern", engagement: { ...ENGAGEMENT, type: "intern", label: "Intern", term: "12 or 24 weeks" }, title: "Software Engineer Intern" };
const VOLUNTEER = { ...ROLE, slug: "content-communications-volunteer", code: "CC", engagement_type: "volunteer", engagement: { ...ENGAGEMENT, type: "volunteer", label: "Volunteer", term: "8 to 24 weeks, project-based", hours_min: 10, hours_target: 15 }, title: "Content & Communications Volunteer", family: "Marketing and communications", summary: "Tell the Paxofi story clearly." };
const ROLES = [ROLE, INTERN, { ...ROLE, slug: "hr-officer-fellow", code: "HR", title: "HR Officer Fellow", family: "People and HR", summary: "Support recruitment and onboarding." }, VOLUNTEER];
// Applications are open unless a test sets this to a closed status (CAREERS_APPLICATIONS_OPEN_FROM).
let STATUS = { applications_open: true, opens_at: null, opens_on: null, cohort: "Cohort 1", cohort1_closes_on: "Sunday 15 November 2026" };

let api;
before(async () => {
  api = createServer((request, response) => {
    const path = new URL(request.url, "http://localhost").pathname;
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    if (path === "/api/v1/careers/roles") return json(200, { data: ROLES });
    if (path === "/api/v1/careers/status") return json(200, { data: STATUS });
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
    assert.equal(await page.locator(".role-card").count(), 3, "roles are grouped by role family: Software Engineer (Fellow, Intern), HR Officer and Content & Communications");
    await page.getByRole("link", { name: "Software Engineer Intern: view role and apply" }).waitFor();
    await page.getByRole("link", { name: "Content & Communications Volunteer: view role and apply" }).waitFor();
    await page.getByText("Cohort 1 applications close on Sunday 15 November 2026").waitFor();
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
    // Arrives from a tagged campaign link on the home page, then opens the role.
    await page.goto(`${BASE}/?utm_source=linkedin&utm_medium=social&utm_campaign=pif-2026`);
    await page.getByRole("link", { name: ROLE.title }).click();
    await page.waitForURL(`**/roles/${ROLE.slug}`);
    assert.equal(await page.locator("h1").textContent(), ROLE.title);
    assert.ok(await page.getByText("Unpaid: a learning and development programme, not employment").isVisible());
    assert.ok(await page.getByText("24 programme weeks").first().isVisible());
    assert.ok(await page.getByText("At least 15 hours a week (20 recommended)").isVisible());
    await assertAccessible(page, "role page");

    await fillApplication(page);
    await page.getByLabel("How did you hear about this role? (optional)").selectOption("linkedin");
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
    assert.equal(seen.apply.source, "linkedin");
    assert.deepEqual([seen.apply.utm_source, seen.apply.utm_medium, seen.apply.utm_campaign], ["linkedin", "social", "pif-2026"], "the campaign of the landing link is kept");
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

  test("each engagement type shows its own hours, term and hours choices", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/roles/${VOLUNTEER.slug}`);
    assert.ok(await page.getByText("At least 10 hours a week (15 recommended)").isVisible());
    assert.ok(await page.getByText("8 to 24 weeks, project-based").first().isVisible());
    const options = await page.getByLabel("Hours a week you can commit").locator("option").evaluateAll((nodes) => nodes.map((n) => n.value));
    assert.deepEqual(options, ["10", "15", "20", "25", "30", "35", "40"]);
    assert.equal(await page.getByLabel("Hours a week you can commit").inputValue(), "15", "Volunteers default to their target of 15");
    await page.goto(`${BASE}/roles/${INTERN.slug}`);
    assert.ok(await page.getByText("12 or 24 weeks").first().isVisible());
    assert.deepEqual(await page.getByLabel("Hours a week you can commit").locator("option").evaluateAll((nodes) => nodes.map((n) => n.value)), ["15", "20", "25", "30", "35", "40"]);
    assert.equal(await page.getByLabel("Hours a week you can commit").inputValue(), "20");
    await assertAccessible(page, "intern role page");
    await page.context().close();
  });

  test("before applications open the site shows the opening date and no form, except for staff test mode", async () => {
    STATUS = { applications_open: false, opens_at: "2026-11-02T00:00:00+01:00", opens_on: "Monday 2 November 2026", cohort: "Cohort 1", cohort1_closes_on: "Sunday 15 November 2026" };
    try {
      const page = await newPage();
      const seen = await mockApplyApi(page, { apply: () => ({ status: 201, body: { data: { reference: "PIF-TEST01", role: ROLE.title } } }) });
      await page.goto(BASE + "/");
      await page.getByText("Applications open on Monday 2 November 2026.").waitFor();
      await assertAccessible(page, "home before opening");

      await page.goto(`${BASE}/roles/${ROLE.slug}`);
      await page.getByText("Applications open on Monday 2 November 2026.").waitFor();
      assert.equal(await page.getByRole("button", { name: "Submit application" }).count(), 0, "no form before opening");
      assert.equal(await page.getByLabel("Full name").count(), 0);

      await page.goto(`${BASE}/roles/${ROLE.slug}?test`);
      await page.getByLabel("Test code (staff only)").fill("controlled-test-code-0123456789");
      await fillApplication(page);
      await page.getByRole("button", { name: "Submit application" }).click();
      await page.getByRole("heading", { name: "Application received" }).waitFor();
      assert.equal(seen.apply.test_code, "controlled-test-code-0123456789");
      await page.context().close();
    } finally {
      STATUS = { applications_open: true, opens_at: null, opens_on: null, cohort: "Cohort 1", cohort1_closes_on: "Sunday 15 November 2026" };
    }
  });

  test("after the Cohort 1 window the site says later applications go to the talent pool and the next cohort", async () => {
    STATUS = { applications_open: true, opens_at: null, opens_on: null, cohort: "Cohort 2", cohort1_closes_on: "Sunday 15 November 2026" };
    try {
      const page = await newPage();
      await page.goto(BASE + "/");
      await page.getByText("Cohort 1 applications have closed.").waitFor();
      await page.context().close();
    } finally {
      STATUS = { applications_open: true, opens_at: null, opens_on: null, cohort: "Cohort 1", cohort1_closes_on: "Sunday 15 November 2026" };
    }
  });

  test("a refusal before opening is shown to the candidate", async () => {
    const page = await newPage();
    await mockApplyApi(page, { apply: () => ({ status: 403, body: { success: false, error: { code: "APPLICATIONS_NOT_OPEN", message: "Applications are not open yet. They open on Monday 2 November 2026." } } }) });
    await page.goto(`${BASE}/roles/${ROLE.slug}`);
    await fillApplication(page);
    await page.getByRole("button", { name: "Submit application" }).click();
    await page.getByText("Applications are not open yet. They open on Monday 2 November 2026.").first().waitFor();
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
