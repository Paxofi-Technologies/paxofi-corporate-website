// Content freshness (D-025): the website answers retired addresses with a
// permanent redirect read from the API (a local stand-in here), and staff
// manage review dates and redirects under Content (the admin API is faked in
// the browser).
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const PORT = Number(process.env.E2E_PORT || 3123) + 16;
const API_PORT = PORT + 1;
const API = `http://127.0.0.1:${API_PORT}/api/v1`;

const REDIRECTS = [
  { from: "/insights/old-article", to: "/insights/new-article" },
  { from: "/old-brochure", to: "https://paxofi.com/brochure.pdf" },
  { from: "/admin/old", to: "/" },
];

let api;
before(async () => {
  api = createServer((request, response) => {
    const url = new URL(request.url, "http://localhost");
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    if (url.pathname === "/api/v1/redirects") return json(200, { success: true, data: REDIRECTS, meta: {} });
    return json(404, { success: false, error: { code: "NOT_FOUND", message: "Not found." } });
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(PORT, { apiBase: API });

const cors = (base) => ({ "Access-Control-Allow-Origin": base, "Access-Control-Allow-Credentials": "true", "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept", Vary: "Origin" });
const session = (permissions) => ({ id: "1", email: "ada@paxofi.com", display_name: "Ada Admin", status: "active", role: "administrator", role_label: "Administrator", permissions, two_factor_enabled: true });

describe("content freshness", () => {
  test("retired addresses are sent on with a permanent redirect", async () => {
    const moved = await fetch(`${BASE}/Insights/Old-Article?utm_source=linkedin`, { redirect: "manual" });
    assert.equal(moved.status, 301);
    const location = new URL(moved.headers.get("location"), BASE);
    assert.equal(location.pathname + location.search, "/insights/new-article?utm_source=linkedin", "any case, and campaign tags survive");
    // Next.js drops a trailing slash first (308); the next request is then redirected.
    const slash = await fetch(`${BASE}/insights/old-article/`, { redirect: "manual" });
    assert.equal(slash.status, 308);
    assert.equal((await fetch(new URL(slash.headers.get("location"), BASE), { redirect: "manual" })).status, 301);
    const external = await fetch(`${BASE}/old-brochure`, { redirect: "manual" });
    assert.equal(external.status, 301);
    assert.equal(external.headers.get("location"), "https://paxofi.com/brochure.pdf");
    assert.notEqual((await fetch(`${BASE}/admin/old`, { redirect: "manual" })).status, 301, "the staff area is never redirected");
    assert.equal((await fetch(`${BASE}/insights/old-article`, { method: "HEAD", redirect: "manual" })).status, 301);
    assert.equal((await fetch(`${BASE}/about`, { redirect: "manual" })).status, 200, "other pages are untouched");
  });

  test("staff see what is due and mark it reviewed", async () => {
    const page = await newPage();
    let items = [
      { type: "product", type_label: "Product", key: "p1", title: "PaxofiCloud", path: "/products#paxoficloud", changed_at: null, review_by: null, last_reviewed_at: null, last_reviewed_by: null, note: null, status: "none" },
      { type: "page", type_label: "Page text", key: "about", title: "About", path: "/about", changed_at: null, review_by: "2026-09-30", last_reviewed_at: null, last_reviewed_by: null, note: null, status: "overdue" },
      { type: "article", type_label: "Article", key: "a1", title: "Introducing PIF 2026", path: "/insights/introducing-pif-2026", changed_at: null, review_by: "2027-04-01", last_reviewed_at: "2026-10-01 09:00:00", last_reviewed_by: "Ada Admin", note: null, status: "ok" },
    ];
    items = [items[1], items[0], items[2]];
    const sent = [];
    await page.route(`${API}/admin/**`, async (route) => {
      const request = route.request();
      if (request.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors(BASE) });
      const reply = (status, body, meta = {}) => route.fulfill({ status, headers: cors(BASE), contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta } : { success: false, error: body }) });
      const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
      if (path === "/session") return reply(200, session(["content.edit", "content.publish"]));
      if (path === "/reviews") return reply(200, items, { today: "2026-10-06" });
      if (path === "/reviews/page/about") {
        const body = request.postDataJSON();
        sent.push(body);
        if (body.action === "reviewed") return reply(200, { ...items[0], review_by: "2027-10-06", last_reviewed_at: "2026-10-06 10:00:00", last_reviewed_by: "Ada Admin", status: "ok" });
        return reply(200, { ...items[0], review_by: body.review_by, note: body.note, status: "ok" });
      }
      return reply(404, { code: "NOT_FOUND", message: "Not found." });
    });

    await page.goto(`${BASE}/admin/content?tab=reviews`);
    const summary = page.getByRole("list", { name: "Review dates" });
    await summary.waitFor();
    assert.match(await summary.textContent(), /Overdue\s*1.*Due soon\s*0.*No date\s*1.*Up to date\s*1/s);
    const rows = page.locator(".admin-table tbody tr");
    assert.equal(await rows.count(), 3);
    assert.equal(await rows.first().locator(".status-pill").textContent(), "Overdue");
    assert.equal(await page.getByRole("link", { name: /View About on the website/ }).getAttribute("href"), "/about");

    await page.getByLabel("Show").selectOption("attention");
    assert.equal(await rows.count(), 1, "only what needs attention");
    await assertAccessible(page, "content reviews");

    const about = rows.first();
    await about.getByLabel("Next review of Page text: About").selectOption("12");
    await about.getByRole("button", { name: /^Mark reviewed\s*: Page text: About$/ }).click();
    await page.getByText("About marked reviewed.").waitFor();
    assert.deepEqual(sent[0], { action: "reviewed", months: 12, note: null });

    await page.getByLabel("Show").selectOption("all");
    const aboutAgain = page.locator(".admin-table tbody tr", { hasText: "About" });
    assert.equal(await aboutAgain.locator(".status-pill").textContent(), "Up to date");
    assert.match(await aboutAgain.textContent(), /6 Oct 2027/);
    await aboutAgain.getByText("Set a date or note").click();
    await aboutAgain.getByLabel("Review by").fill("2026-10-01");
    await aboutAgain.getByRole("button", { name: /^Save/ }).click();
    assert.equal(await aboutAgain.getByLabel("Review by").evaluate((input) => input.matches(":invalid")), true, "the browser refuses a past date");
    assert.equal(sent.length, 1, "nothing was sent");
    await aboutAgain.getByLabel("Review by").fill("2027-01-15");
    await aboutAgain.getByLabel("Note (optional)").fill("Team page changes in January");
    await aboutAgain.getByRole("button", { name: /^Save/ }).click();
    await page.getByText("Review date for About saved.").waitFor();
    assert.deepEqual(sent.at(-1), { review_by: "2027-01-15", note: "Team page changes in January" });
    await page.context().close();
  });

  test("administrators add and delete redirects; editors only see them", async () => {
    const page = await newPage();
    let redirects = [];
    let permissions = ["content.edit", "content.publish"];
    const sent = [];
    await page.route(`${API}/admin/**`, async (route) => {
      const request = route.request();
      if (request.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors(BASE) });
      const reply = (status, body) => route.fulfill({ status, headers: cors(BASE), contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta: {} } : { success: false, error: body }) });
      const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
      if (path === "/session") return reply(200, session(permissions));
      if (path === "/redirects" && request.method() === "GET") return reply(200, redirects);
      if (path === "/redirects" && request.method() === "POST") {
        const body = request.postDataJSON();
        sent.push(body);
        if (body.from === "/about") return reply(422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { from: "That address is a page the website needs. Choose the address of a page that no longer exists." } } });
        const created = { id: "r0r0r0r0-0000-4000-8000-000000000001", from: body.from, to: body.to, note: body.note, created_by: "Ada Admin", created_at: "2026-10-06 10:00:00", updated_at: "2026-10-06 10:00:00" };
        redirects = [created];
        return reply(201, created);
      }
      if (path.startsWith("/redirects/") && request.method() === "DELETE") {
        redirects = [];
        return reply(200, { deleted: true });
      }
      return reply(404, { code: "NOT_FOUND", message: "Not found." });
    });

    await page.goto(`${BASE}/admin/content?tab=redirects`);
    await page.getByRole("heading", { name: "Add a redirect" }).waitFor();
    await page.getByText("No redirects yet.").waitFor();
    await page.getByLabel("Old address").fill("/about");
    await page.getByLabel("Send visitors to").fill("/");
    await page.getByRole("button", { name: "Add redirect" }).click();
    await page.getByText("That address is a page the website needs.", { exact: false }).waitFor();
    assert.equal(await page.getByLabel("Old address").getAttribute("aria-invalid"), "true");

    await page.getByLabel("Old address").fill("/insights/old-article");
    await page.getByLabel("Send visitors to").fill("/insights/new-article");
    await page.getByLabel("Note (optional)").fill("Article renamed");
    await page.getByRole("button", { name: "Add redirect" }).click();
    await page.getByText("Visitors to /insights/old-article now go to /insights/new-article.", { exact: false }).waitFor();
    assert.deepEqual(sent.at(-1), { from: "/insights/old-article", to: "/insights/new-article", note: "Article renamed" });
    assert.equal(await page.getByLabel("Old address").inputValue(), "", "the form is cleared");
    await assertAccessible(page, "redirects");

    page.once("dialog", (dialog) => dialog.accept());
    await page.getByRole("button", { name: "Delete redirect from /insights/old-article" }).click();
    await page.getByText("Redirect from /insights/old-article deleted.").waitFor();

    permissions = ["content.edit"];
    await page.reload();
    await page.getByText("Only administrators can add or delete redirects.").waitFor();
    assert.equal(await page.getByRole("heading", { name: "Add a redirect" }).count(), 0);
    await page.context().close();
  });
});
