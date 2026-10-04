// End-to-end, accessibility and security-header checks against a production
// build (`npm run build` first). Starts `next start` itself; the contact API is
// intercepted in the browser, so no backend is needed.
//
//   npm run test:e2e
//
// E2E_BROWSER selects the engine (see harness.mjs).
import { describe, test } from "node:test";
import assert from "node:assert/strict";
import { API_BASE, assertAccessible, startHarness } from "./harness.mjs";

const { base: BASE, newPage } = startHarness(Number(process.env.E2E_PORT || 3123));
const CONTACT_URL = `${API_BASE}/forms/contact/submit`;
const ROUTES = ["/", "/about", "/services", "/products", "/careers", "/contact", "/privacy", "/terms"];

// Phone, tablet, laptop and wide desktop. The menu collapses at 860px and below.
const VIEWPORTS = [
  { name: "phone", width: 360, height: 740, collapsedMenu: true },
  { name: "tablet", width: 768, height: 1024, collapsedMenu: true },
  { name: "laptop", width: 1280, height: 800, collapsedMenu: false },
  { name: "desktop", width: 1920, height: 1080, collapsedMenu: false },
];

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

      await assertAccessible(page);
      await page.context().close();
    });
  }

  test("every page's content is in the server HTML and readable without JavaScript", async () => {
    // Guards against a route-level Suspense/loading fallback, which would ship
    // the content hidden until a script reveals it.
    const page = await newPage({ javaScriptEnabled: false });
    for (const path of ROUTES) {
      await page.goto(BASE + path);
      assert.ok(await page.locator("main h1").isVisible(), `${path}: h1 visible without JavaScript`);
      assert.equal(await page.locator("main [hidden] h1, template").count(), 0, `${path}: no deferred content`);
    }
    await page.context().close();
  });

  test("no page triggers a Content-Security-Policy violation and every page hydrates", async () => {
    const page = await newPage();
    await page.addInitScript(() => {
      window.__cspViolations = [];
      document.addEventListener("securitypolicyviolation", (e) => window.__cspViolations.push(`${e.violatedDirective} ${e.blockedURI}`));
    });
    for (const path of ROUTES) {
      await page.goto(BASE + path, { waitUntil: "networkidle" });
      // The header's menu button only works once React has hydrated the page.
      await page.waitForFunction(() => Object.keys(document.querySelector(".menu-toggle") ?? {}).some((k) => k.startsWith("__react")));
      assert.deepEqual(await page.evaluate(() => window.__cspViolations), [], path);
    }
    await page.context().close();
  });

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
    assert.match(csp, /connect-src 'self' https:\/\/api\.e2e\.test;/, "connect-src names only the configured API origin");
    assert.match(csp, /script-src 'self' 'nonce-[A-Za-z0-9+/=]+' 'strict-dynamic'/);
    assert.doesNotMatch(csp, /unsafe-inline|unsafe-eval/);
    const second = (await fetch(`${BASE}/`)).headers.get("content-security-policy");
    assert.notEqual(second, csp, "a new nonce for every response");
    assert.equal(response.headers.get("cross-origin-embedder-policy"), "require-corp");
    assert.match(response.headers.get("strict-transport-security") ?? "", /max-age=\d+/);
    assert.equal(response.headers.get("x-content-type-options"), "nosniff");
    assert.equal(response.headers.get("x-frame-options"), "DENY");
    assert.equal(response.headers.get("cross-origin-opener-policy"), "same-origin");
    assert.equal(response.headers.get("cross-origin-resource-policy"), "same-origin");
    assert.equal(response.headers.get("x-powered-by"), null);
  });

  test("pages cannot be kept by a shared cache after a deployment", async () => {
    for (const path of ROUTES) {
      const cacheControl = (await fetch(BASE + path)).headers.get("cache-control") ?? "";
      assert.doesNotMatch(cacheControl, /s-maxage|public/, `${path}: ${cacheControl}`);
      assert.match(cacheControl, /no-store|no-cache|max-age=0/, `${path}: ${cacheControl}`);
    }
  });

  test("security.txt publishes a security contact (RFC 9116)", async () => {
    const response = await fetch(`${BASE}/.well-known/security.txt`);
    assert.equal(response.status, 200);
    const body = await response.text();
    assert.match(body, /^Contact: mailto:\S+@paxofi\.com$/m);
    const expires = new Date(body.match(/^Expires: (.+)$/m)?.[1] ?? "");
    assert.ok(expires > new Date(), "security.txt has expired; set a new Expires date (at most a year ahead)");
  });

  test("no cookies are set on any page", async () => {
    const page = await newPage();
    for (const path of ROUTES) await page.goto(BASE + path);
    assert.deepEqual(await page.context().cookies(), []);
    await page.context().close();
  });

  test("release.txt identifies the deployed release", async () => {
    const response = await fetch(`${BASE}/release.txt`);
    assert.equal(response.status, 200);
    assert.ok((await response.text()).trim().length > 0);
  });

  test("robots.txt and sitemap.xml list the public site", async () => {
    const robots = await (await fetch(`${BASE}/robots.txt`)).text();
    assert.match(robots, /Sitemap: https?:\/\/\S+\/sitemap\.xml/);
    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    for (const path of ROUTES.filter((p) => p !== "/")) assert.ok(sitemap.includes(`${path}</loc>`), path);
  });

  test("every page has a large social share image (LinkedIn, WhatsApp, X)", async () => {
    const page = await newPage();
    for (const path of ROUTES) {
      await page.goto(BASE + path);
      const image = await page.locator('meta[property="og:image"]').getAttribute("content");
      assert.match(image ?? "", /^https:\/\/\S+\/og-image\.png$/, `${path}: absolute og:image`);
      assert.equal(await page.locator('meta[property="og:image:width"]').getAttribute("content"), "1200");
      assert.equal(await page.locator('meta[property="og:image:height"]').getAttribute("content"), "630");
      assert.ok(await page.locator('meta[property="og:description"]').getAttribute("content"), `${path}: og:description`);
      assert.equal(await page.locator('meta[name="twitter:card"]').getAttribute("content"), "summary_large_image");
    }
    await page.context().close();
    const response = await fetch(`${BASE}/og-image.png`);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get("content-type"), "image/png");
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

    // Menu state updates after React re-renders, so wait rather than read once.
    await nav.getByRole("link", { name: "About" }).waitFor({ state: "hidden", timeout: 5000 });
    await page.getByRole("button", { name: "Open menu" }).click();
    await nav.getByRole("link", { name: "About" }).waitFor({ state: "visible", timeout: 5000 });
    assert.equal(await nav.getByRole("link", { name: "About" }).getAttribute("aria-current"), "page");
    await page.keyboard.press("Escape");
    await nav.getByRole("link", { name: "About" }).waitFor({ state: "hidden", timeout: 5000 });
    await page.context().close();
  });

  test("careers links go to the careers site, which handles all applications", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/careers`);
    const hrefs = await page.locator('main a[href*="careers.paxofi"]').evaluateAll((links) => links.map((a) => a.getAttribute("href")));
    assert.ok(hrefs.length >= 1);
    for (const href of hrefs) assert.equal(href, "https://careers.paxofi.com");
    assert.equal(await page.locator("main form").count(), 0, "no application form on the corporate site");
    await page.context().close();
  });

  test("About tells the company story; Careers explains the fellowship and how to apply", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/about`);
    for (const heading of ["We build digital solutions that solve real problems.", "From a vision to a growing reality.", "Our vision", "Our mission", "What we stand for.", "Want to build with us?"]) {
      await page.getByRole("heading", { name: heading }).waitFor();
    }
    await page.getByRole("link", { name: "Explore careers" }).first().click();
    await page.waitForURL(`${BASE}/careers`);
    await page.getByRole("heading", { name: "Your next opportunity could start here." }).waitFor();
    assert.equal(await page.locator(".role-list li").count(), 7, "the seven PIF role families");
    await page.getByText("No stipend or salary at this time", { exact: false }).waitFor();
    const faq = page.getByText("Is the fellowship paid?");
    await faq.click();
    await page.getByText("does not offer a stipend, allowance or salary", { exact: false }).waitFor();
    assert.equal(await page.getByRole("link", { name: "hr@paxofi.com" }).getAttribute("href"), "mailto:hr@paxofi.com");
    await assertAccessible(page, "on careers with an open answer");
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

describe("responsive layout", () => {
  for (const viewport of VIEWPORTS) {
    test(`${viewport.name} (${viewport.width}px): every page fits the screen and the menu is usable`, async () => {
      const page = await newPage({ viewport: { width: viewport.width, height: viewport.height } });
      for (const path of ROUTES) {
        await page.goto(BASE + path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        assert.ok(overflow <= 0, `${path}: page is ${overflow}px wider than the screen`);
        assert.ok(await page.locator("h1").isVisible(), `${path}: heading visible`);

        const toggle = page.locator(".menu-toggle");
        const about = page.getByRole("navigation", { name: "Main" }).getByRole("link", { name: "About" });
        assert.equal(await toggle.isVisible(), viewport.collapsedMenu, `${path}: menu button`);
        assert.equal(await about.isVisible(), !viewport.collapsedMenu, `${path}: inline navigation`);
      }
      await page.context().close();
    });
  }

  test("form fields are large enough not to trigger zoom on phones", async () => {
    const page = await newPage({ viewport: { width: 360, height: 740 } });
    await page.goto(`${BASE}/contact`);
    const sizes = await page.locator("main input:not([type=hidden]):not([tabindex='-1']), main textarea").evaluateAll((fields) =>
      fields.filter((f) => f.offsetParent !== null).map((f) => parseFloat(getComputedStyle(f).fontSize)),
    );
    assert.ok(sizes.length >= 4);
    for (const size of sizes) assert.ok(size >= 16, `field font-size ${size}px`);
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

  test("a submission before the script loads never puts details in the URL", async () => {
    const page = await newPage({ javaScriptEnabled: false });
    await page.goto(`${BASE}/contact`);
    assert.equal(await page.locator("form.contact-form").getAttribute("method"), "post");
    // Browsers render <noscript> only when scripting is off in the parser, which
    // Playwright's javaScriptEnabled does not emulate, so check the markup.
    assert.match(await page.locator("form.contact-form noscript").textContent(), /Sending this form needs JavaScript\./);
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
