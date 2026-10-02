// End-to-end, accessibility and security-header checks against a production
// build (`npm run build` first). Starts `next start` itself; the contact API is
// intercepted in the browser, so no backend is needed.
//
//   npm run test:e2e
//
// Set PLAYWRIGHT_CHROMIUM_PATH to use a preinstalled Chromium.
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { setTimeout as sleep } from "node:timers/promises";
import { chromium } from "playwright";
import AxeBuilder from "@axe-core/playwright";

const PORT = Number(process.env.E2E_PORT || 3123);
const BASE = `http://127.0.0.1:${PORT}`;
const API_BASE = "https://api.e2e.test/api/v1";
const CONTACT_URL = `${API_BASE}/forms/contact/submit`;
const ROUTES = ["/", "/about", "/services", "/products", "/careers", "/contact", "/privacy", "/terms"];

let server;
let browser;

before(async () => {
  const root = new URL("..", import.meta.url).pathname;
  // Own process group, so the whole server tree can be stopped afterwards.
  server = spawn(`${root}node_modules/.bin/next`, ["start", "-p", String(PORT), "-H", "127.0.0.1"], {
    cwd: root,
    detached: true,
    env: { ...process.env, API_BASE_URL: API_BASE, NODE_ENV: "production" },
    stdio: ["ignore", "ignore", "inherit"],
  });
  for (let attempt = 0; attempt < 60; attempt++) {
    try {
      if ((await fetch(BASE)).ok) break;
    } catch {
      // server not listening yet
    }
    await sleep(500);
  }
  browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined });
});

after(async () => {
  await browser?.close();
  if (server?.pid) process.kill(-server.pid, "SIGTERM");
});

/** A page in its own browser context (axe-core requires one per page). */
async function newPage(options = {}) {
  const context = await browser.newContext(options);
  return context.newPage();
}

/** Answers the contact API (including the CORS preflight) inside the browser. */
async function mockContactApi(page, respond) {
  const requests = [];
  await page.route(CONTACT_URL, async (route) => {
    const cors = {
      "Access-Control-Allow-Origin": BASE,
      "Access-Control-Allow-Methods": "POST, OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type, Accept",
    };
    if (route.request().method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
    requests.push(route.request().postDataJSON());
    const { status, body } = respond();
    return route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(body) });
  });
  return requests;
}

describe("public pages", () => {
  for (const path of ROUTES) {
    test(`${path} renders, has SEO metadata and no accessibility violations`, async () => {
      const page = await newPage();
      const response = await page.goto(BASE + path);
      assert.equal(response.status(), 200);
      assert.equal(await page.locator("h1").count(), 1, "exactly one h1");
      assert.match(await page.title(), /Paxofi Technologies/);
      const canonical = await page.locator('link[rel="canonical"]').getAttribute("href");
      assert.ok(canonical?.endsWith(path === "/" ? "" : path) || canonical?.endsWith("/"), `canonical ${canonical}`);
      assert.ok(await page.locator('meta[name="description"]').getAttribute("content"));
      assert.ok(await page.locator('meta[property="og:title"]').getAttribute("content"));

      const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]).analyze();
      const summary = results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`);
      assert.deepEqual(summary, [], "WCAG 2.1 AA violations");
      await page.context().close();
    });
  }

  test("unknown paths return 404 with the site's not-found page", async () => {
    const page = await newPage();
    const response = await page.goto(`${BASE}/does-not-exist`);
    assert.equal(response.status(), 404);
    await page.getByRole("heading", { name: "Page not found." }).waitFor();
    await page.context().close();
  });

  test("security headers are sent and the framework is not advertised", async () => {
    const response = await fetch(`${BASE}/`);
    const csp = response.headers.get("content-security-policy") ?? "";
    assert.match(csp, /frame-ancestors 'none'/);
    assert.match(csp, /object-src 'none'/);
    assert.match(response.headers.get("strict-transport-security") ?? "", /max-age=\d+/);
    assert.equal(response.headers.get("x-content-type-options"), "nosniff");
    assert.equal(response.headers.get("x-frame-options"), "DENY");
    assert.equal(response.headers.get("x-powered-by"), null);
  });

  test("robots.txt and sitemap.xml list the public site", async () => {
    const robots = await (await fetch(`${BASE}/robots.txt`)).text();
    assert.match(robots, /Sitemap: https?:\/\/\S+\/sitemap\.xml/);
    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    for (const path of ROUTES.filter((p) => p !== "/")) assert.ok(sitemap.includes(`${path}</loc>`), path);
  });

  test("the home page publishes Organization structured data", async () => {
    const page = await newPage();
    await page.goto(BASE);
    const data = JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());
    assert.equal(data["@type"], "Organization");
    assert.equal(data.name, "Paxofi Technologies LTD");
    await page.context().close();
  });
});

describe("navigation", () => {
  test("keyboard users can skip to the main content", async () => {
    const page = await newPage();
    await page.goto(BASE);
    await page.keyboard.press("Tab");
    const skip = page.getByRole("link", { name: "Skip to content" });
    assert.ok(await skip.evaluate((el) => el === document.activeElement));
    await page.keyboard.press("Enter");
    assert.equal(await page.evaluate(() => document.activeElement?.id), "main");
    await page.context().close();
  });

  test("the mobile menu opens, navigates and marks the current page", async () => {
    const page = await newPage({ viewport: { width: 390, height: 844 } });
    await page.goto(BASE);
    const nav = page.getByRole("navigation", { name: "Main" });
    const toggle = page.getByRole("button", { name: "Open menu" });
    assert.equal(await nav.getByRole("link", { name: "About" }).isVisible(), false);

    await toggle.click();
    assert.equal(await page.getByRole("button", { name: "Close menu" }).getAttribute("aria-expanded"), "true");
    await nav.getByRole("link", { name: "About" }).click();
    await page.waitForURL(`${BASE}/about`);

    assert.equal(await nav.getByRole("link", { name: "About" }).isVisible(), false, "menu closes after navigating");
    await page.getByRole("button", { name: "Open menu" }).click();
    assert.equal(await nav.getByRole("link", { name: "About" }).getAttribute("aria-current"), "page");
    await page.keyboard.press("Escape");
    assert.equal(await nav.getByRole("link", { name: "About" }).isVisible(), false, "Escape closes the menu");
    await page.context().close();
  });

  test("desktop navigation reaches every section", async () => {
    const page = await newPage({ viewport: { width: 1280, height: 800 } });
    await page.goto(BASE);
    const nav = page.getByRole("navigation", { name: "Main" });
    for (const [name, path] of [["About", "/about"], ["Services", "/services"], ["Products", "/products"], ["Careers", "/careers"], ["Talk to us", "/contact"]]) {
      await nav.getByRole("link", { name }).click();
      await page.waitForURL(BASE + path);
    }
    await page.context().close();
  });
});

describe("contact journey", () => {
  async function fillForm(page) {
    await page.goto(`${BASE}/contact`);
    await page.getByLabel("Name").fill("Ada Lovelace");
    await page.getByLabel("Email").fill("ada@example.com");
    await page.getByLabel("Company (optional)").fill("Analytical Engines");
    await page.getByLabel("How can we help?").fill("We would like to discuss a payments integration.");
  }

  test("a valid enquiry is sent to the API and confirmed", async () => {
    const page = await newPage();
    const requests = await mockContactApi(page, () => ({ status: 201, body: { success: true, data: { id: "e2e" } } }));
    await fillForm(page);
    await page.getByRole("button", { name: "Send enquiry" }).click();
    await page.getByText("Thanks — your enquiry has been received.").waitFor();

    assert.equal(requests.length, 1);
    assert.deepEqual(requests[0], {
      name: "Ada Lovelace",
      email: "ada@example.com",
      company: "Analytical Engines",
      message: "We would like to discuss a payments integration.",
      website: "",
    });
    assert.equal(await page.getByLabel("Name").inputValue(), "", "form is reset");
    await page.context().close();
  });

  test("field errors from the API are shown next to the field and focused", async () => {
    const page = await newPage();
    await mockContactApi(page, () => ({
      status: 422,
      body: { success: false, error: { message: "Please correct the highlighted fields.", details: { fields: { email: "Enter a valid email address." } } } },
    }));
    await fillForm(page);
    await page.getByRole("button", { name: "Send enquiry" }).click();
    await page.getByText("Enter a valid email address.").waitFor();
    const email = page.getByLabel("Email");
    assert.equal(await email.getAttribute("aria-invalid"), "true");
    assert.ok(await email.evaluate((el) => el === document.activeElement));
    await page.context().close();
  });

  test("an unreachable API gives a helpful message", async () => {
    const page = await newPage();
    await page.route(CONTACT_URL, (route) => route.abort("connectionrefused"));
    await fillForm(page);
    await page.getByRole("button", { name: "Send enquiry" }).click();
    await page.getByText("We could not send your enquiry.").waitFor();
    await page.context().close();
  });
});
