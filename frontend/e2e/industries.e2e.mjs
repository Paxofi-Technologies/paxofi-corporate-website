// Industries and product status labels (D-022): the public pages read the API
// on the server (a local stand-in here), and staff edit industries in the staff
// area (the admin API is faked in the browser).
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const PORT = Number(process.env.E2E_PORT || 3123) + 12;
const API_PORT = PORT + 1;
const API = `http://127.0.0.1:${API_PORT}/api/v1`;

const PRODUCTS = [
  { slug: "paxofi-pay", name: "Paxofi Pay", label: "Paxofi Product", status: "planned", icon: "shield-check", summary: "Digital payments infrastructure.", points: [], sort_order: 10 },
  { slug: "paxoficloud", name: "PaxofiCloud", label: "Paxofi Product", status: "in_development", icon: "cloud", summary: "Domains, hosting and cloud servers in one account.", points: [], sort_order: 15 },
];
const SERVICES = [
  { slug: "software-web-engineering", name: "Software & Web Engineering", label: null, icon: "code", summary: "Web platforms and business applications.", points: [], sort_order: 10 },
];
const INDUSTRIES = [
  {
    slug: "education",
    name: "Education",
    label: null,
    icon: "graduation-cap",
    summary: "Learning platforms and student portals.",
    description: "Schools need systems people can use.\n\nWe build learning platforms. <script>window.hacked = true</script>",
    points: ["Learning and training platforms", "Student and parent portals"],
    related: [
      { type: "service", slug: "software-web-engineering", name: "Software & Web Engineering", summary: "Web platforms and business applications.", icon: "code", status: null },
      { type: "product", slug: "paxoficloud", name: "PaxofiCloud", summary: "Domains, hosting and cloud servers in one account.", icon: "cloud", status: "in_development" },
    ],
    sort_order: 20,
  },
  { slug: "healthcare", name: "Healthcare", label: null, icon: "heart-pulse", summary: "Health information systems.", description: null, points: [], related: [], sort_order: 30 },
];

let api;
before(async () => {
  api = createServer((request, response) => {
    const url = new URL(request.url, "http://localhost");
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    const list = { "/api/v1/products": PRODUCTS, "/api/v1/services": SERVICES, "/api/v1/industries": INDUSTRIES }[url.pathname];
    if (list) return json(200, { success: true, data: list, meta: {} });
    if (url.pathname === "/api/v1/articles") return json(200, { data: [], meta: { page: 1, per_page: 30, total: 0, total_pages: 1 } });
    return json(404, { success: false, error: { code: "NOT_FOUND", message: "Not found." } });
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(PORT, { apiBase: API });

describe("industries", () => {
  test("the list links each industry to its own page and is in the menu", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/industries`);
    await page.getByRole("heading", { level: 1, name: "Technology shaped around your sector." }).waitFor();
    assert.deepEqual(await page.locator(".industry-card h3").allTextContents(), ["Education", "Healthcare"]);
    assert.equal(await page.getByRole("link", { name: "Education" }).getAttribute("href"), "/industries/education");
    assert.equal(await page.getByRole("navigation", { name: "Main" }).getByRole("link", { name: "Industries" }).getAttribute("href"), "/industries");
    await assertAccessible(page, "industries");
    await page.getByRole("link", { name: "Education" }).click();
    await page.waitForURL(`${BASE}/industries/education`);
    await page.context().close();
  });

  test("an industry page shows its description, examples and related published items", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/industries/education`);
    assert.equal(await page.locator("h1").textContent(), "Education");
    assert.equal(await page.title(), "Education | Paxofi Technologies");
    assert.equal(await page.locator('link[rel="canonical"]').getAttribute("href"), "https://corporate.paxofi.com/industries/education");
    assert.equal(await page.locator(".industry-detail .prose p").count(), 2, "two paragraphs");
    assert.ok(await page.getByText("<script>window.hacked = true</script>", { exact: false }).isVisible(), "text stays text");
    assert.equal(await page.evaluate(() => window.hacked), undefined);
    assert.deepEqual(await page.locator(".industry-points li").allTextContents(), ["Learning and training platforms", "Student and parent portals"]);
    const related = page.getByRole("region", { name: "Related services and products" });
    assert.equal(await related.getByRole("link", { name: "Software & Web Engineering" }).getAttribute("href"), "/services#software-web-engineering");
    assert.equal(await related.getByRole("link", { name: "PaxofiCloud" }).getAttribute("href"), "/products#paxoficloud");
    assert.equal((await related.locator(".product-status").textContent()).trim(), "Status: In development");
    await assertAccessible(page, "industry page");

    await page.goto(`${BASE}/industries/healthcare`);
    assert.equal(await page.getByRole("region", { name: "Related services and products" }).count(), 0, "no related section when there is nothing to link");
    assert.equal((await page.goto(`${BASE}/industries/no-such-sector`)).status(), 404);

    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    assert.match(sitemap, /\/industries<\/loc>/);
    assert.match(sitemap, /\/industries\/healthcare<\/loc>/);
    await page.context().close();
  });

  test("products show their status, and related links land on the right card", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/products#paxoficloud`);
    const card = page.locator("#paxoficloud");
    assert.equal(await card.locator("h2").textContent(), "PaxofiCloud");
    assert.equal((await card.locator(".product-status").textContent()).trim(), "Status: In development");
    assert.equal((await page.locator("#paxofi-pay .product-status").textContent()).trim(), "Status: Planned");
    await assertAccessible(page, "products with status labels");
    await page.goto(`${BASE}/services`);
    assert.equal(await page.locator("#software-web-engineering h3").textContent(), "Software & Web Engineering");
    await page.context().close();
  });

  test("staff add an industry with related products and services", async () => {
    const page = await newPage();
    const cors = { "Access-Control-Allow-Origin": BASE, "Access-Control-Allow-Credentials": "true", "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept", Vary: "Origin" };
    const reply = (route, status, body) => route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta: {} } : { success: false, error: body }) });
    const summary = (slug, name, visible = true) => ({ id: `c0c0c0c0-0000-4000-8000-00000000000${slug.length % 10}`, slug, name, visible, has_draft: false, sort_order: 10, status: null, updated_at: null });
    let created = null;
    await page.route(`${API}/admin/**`, async (route) => {
      const request = route.request();
      if (request.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
      const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
      if (path === "/session") return reply(route, 200, { id: "1", email: "ada@paxofi.com", display_name: "Ada Admin", status: "active", role: "administrator", role_label: "Administrator", permissions: ["content.edit", "content.publish"], two_factor_enabled: true });
      if (path === "/media") return reply(route, 200, []);
      if (path === "/catalog/products") return reply(route, 200, [summary("paxofi-pay", "Paxofi Pay"), summary("paxoficloud", "PaxofiCloud", false)]);
      if (path === "/catalog/services") return reply(route, 200, [summary("software-web-engineering", "Software & Web Engineering")]);
      if (path === "/catalog/industries" && request.method() === "POST") {
        created = request.postDataJSON();
        const item = { id: "d1d1d1d1-0000-4000-8000-000000000001", slug: "energy-utilities", name: created.name, visible: false, has_draft: false, sort_order: 110, updated_at: null, content: { ...created, sort_order: 110 } };
        return reply(route, 201, { item, draft: null, revisions: [] });
      }
      if (path === "/catalog/industries/d1d1d1d1-0000-4000-8000-000000000001") {
        return reply(route, 200, { item: { id: "d1d1d1d1-0000-4000-8000-000000000001", slug: "energy-utilities", name: created.name, visible: false, has_draft: false, sort_order: 110, updated_at: null, content: { ...created, sort_order: 110 } }, draft: null, revisions: [] });
      }
      return reply(route, 404, { code: "NOT_FOUND", message: "Not found." });
    });

    await page.goto(`${BASE}/admin/content?tab=industries`);
    await page.getByRole("link", { name: "Add an industry" }).click();
    await page.getByRole("heading", { level: 1, name: "Add an industry" }).waitFor();
    assert.equal(await page.getByLabel("Status").count(), 0, "status is for products only");
    await page.getByLabel("Name").fill("Energy & Utilities");
    await page.getByLabel("Icon").selectOption("lightbulb");
    await page.getByLabel(/^Summary/).fill("Systems for metering, billing and field operations.");
    await page.getByLabel(/^Description/).fill("First paragraph.\n\nSecond paragraph.");
    await page.getByLabel(/^Example solutions/).fill("Metering and billing\nField service apps");
    const related = page.getByRole("group", { name: "Related products and services (optional)" });
    await related.getByLabel("PaxofiCloud (product, hidden)").check();
    await related.getByLabel("Software & Web Engineering (service)").check();
    assert.equal(await page.locator(".admin-preview .industry-card h3").textContent(), "Energy & Utilities");
    assert.equal(await page.locator(".admin-preview .prose p").count(), 2, "description preview");
    await assertAccessible(page, "new industry form");
    await page.getByRole("button", { name: "Add industry (hidden)" }).click();
    await page.waitForURL(/\/admin\/content\/industries\/d1d1d1d1/);
    assert.deepEqual(created.related, ["product:paxoficloud", "service:software-web-engineering"], "in the order ticked");
    assert.equal(created.description, "First paragraph.\n\nSecond paragraph.");
    assert.equal(created.status, null);

    await page.goto(`${BASE}/admin/content/products/new`);
    assert.equal(await page.getByLabel("Status").inputValue(), "planned", "new products start as Planned");
    await page.context().close();
  });
});
