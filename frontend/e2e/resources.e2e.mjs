// The Resources page (D-023): the public page reads the API on the server (a
// local stand-in here), and administrators list documents from the Media page
// (the admin API is faked in the browser).
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const PORT = Number(process.env.E2E_PORT || 3123) + 14;
const API_PORT = PORT + 1;
const API = `http://127.0.0.1:${API_PORT}/api/v1`;
const DOC = "e0e0e0e0-0000-4000-8000-000000000001";
const GUIDE = "e0e0e0e0-0000-4000-8000-000000000002";
const mode = { value: "ok" };

const RESOURCES = [
  { title: "PIF 2026 applicant guide", summary: "Everything applicants need to know.", category: "guide", category_label: "Guides", format: "Word", size_bytes: 40960, path: `/api/v1/media/${GUIDE}/pif-2026-guide.docx`, listed_at: "2026-10-06T10:00:00Z" },
  { title: "Paxofi Pay brochure", summary: "How payments stay <b>certain</b>.", category: "brochure", category_label: "Brochures", format: "PDF", size_bytes: 1258291, path: `/api/v1/media/${DOC}/paxofi-pay-brochure.pdf`, listed_at: "2026-10-06T09:00:00Z" },
];

let api;
before(async () => {
  api = createServer((request, response) => {
    const url = new URL(request.url, "http://localhost");
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    if (url.pathname === "/api/v1/resources") {
      if (mode.value === "down") return json(500, { success: false });
      return json(200, { success: true, data: mode.value === "empty" ? [] : RESOURCES, meta: {} });
    }
    if (url.pathname.startsWith("/api/v1/media/")) return response.writeHead(200, { "content-type": "application/pdf", "content-disposition": "attachment" }).end("%PDF-1.4");
    return json(404, { success: false, error: { code: "NOT_FOUND", message: "Not found." } });
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(PORT, { apiBase: API });

describe("resources", () => {
  test("listed documents are grouped by category with download links", async () => {
    mode.value = "ok";
    const page = await newPage();
    await page.goto(`${BASE}/resources`);
    await page.getByRole("heading", { level: 1, name: "Guides, brochures and documents." }).waitFor();
    assert.deepEqual(await page.locator(".resource-groups h2").allTextContents(), ["Brochures", "Guides"], "fixed category order");
    const brochure = page.getByRole("link", { name: /Download Paxofi Pay brochure/ });
    assert.equal(await brochure.getAttribute("href"), `${API}/media/${DOC}/paxofi-pay-brochure.pdf`);
    assert.equal(await brochure.getAttribute("download"), "");
    assert.match(await brochure.textContent(), /\(PDF, 1\.2 MB\)/);
    assert.ok(await page.getByText("How payments stay <b>certain</b>.").isVisible(), "text stays text");
    assert.equal(await page.getByRole("contentinfo").getByRole("link", { name: "Resources" }).getAttribute("href"), "/resources");
    await assertAccessible(page, "resources");
    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    assert.match(sitemap, /\/resources<\/loc>/);
    await page.context().close();
  });

  test("empty and unavailable states are explained", async () => {
    const page = await newPage();
    mode.value = "empty";
    await page.goto(`${BASE}/resources`);
    await page.getByText("No documents are listed yet.", { exact: false }).waitFor();
    mode.value = "down";
    await page.goto(`${BASE}/resources`);
    await page.getByRole("alert").getByText("We could not load the documents just now.", { exact: false }).waitFor();
    mode.value = "ok";
    await page.context().close();
  });

  test("an administrator lists a document from the Media page", async () => {
    const page = await newPage();
    const cors = { "Access-Control-Allow-Origin": BASE, "Access-Control-Allow-Credentials": "true", "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept", Vary: "Origin" };
    const reply = (route, status, body, meta = {}) => route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta } : { success: false, error: body }) });
    const doc = { id: DOC, kind: "document", filename: "paxofi-pay-brochure.pdf", media_type: "application/pdf", format: "PDF", size_bytes: 1258291, width: null, height: null, alt_text: null, title: "Paxofi Pay brochure", path: `/api/v1/media/${DOC}/paxofi-pay-brochure.pdf`, uploaded_by: "Ada Admin", created_at: "2026-10-06 09:00:00", used_by: [], resource: { listed: false, category: null, summary: null, listed_at: null } };
    const sent = [];
    await page.route(`${API}/admin/**`, async (route) => {
      const request = route.request();
      if (request.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
      const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
      if (path === "/session") return reply(route, 200, { id: "1", email: "ada@paxofi.com", display_name: "Ada Admin", status: "active", role: "administrator", role_label: "Administrator", permissions: ["content.edit", "content.publish"], two_factor_enabled: true });
      if (path === "/media") return reply(route, 200, [doc], { uploads: true, images: true, image_max_bytes: 5242880, document_max_bytes: 10485760, server_max_bytes: null });
      if (path === `/media/${DOC}/resource`) {
        const body = request.postDataJSON();
        sent.push(body);
        if (!body.category) return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { category: "Choose a category for the Resources page." } } });
        return reply(route, 200, { ...doc, used_by: body.listed ? ["the Resources page"] : [], resource: { listed: body.listed, category: body.category, summary: body.summary, listed_at: "2026-10-06 10:00:00" } });
      }
      return reply(route, 404, { code: "NOT_FOUND", message: "Not found." });
    });

    await page.goto(`${BASE}/admin/media`);
    const card = page.locator(".media-card", { hasText: "paxofi-pay-brochure.pdf" });
    await card.getByText("Resources page: not listed").click();
    await card.getByLabel(/^Short description/).fill("How Paxofi Pay keeps payments certain.");
    await card.getByRole("button", { name: "List on the Resources page" }).click();
    await card.getByText("Choose a category for the Resources page.").waitFor();
    await card.getByLabel("Category").selectOption("brochure");
    await card.getByRole("button", { name: "List on the Resources page" }).click();
    await card.getByText("Used by the Resources page").waitFor();
    assert.ok(await card.getByRole("button", { name: /^Delete/ }).isDisabled(), "a listed document cannot be deleted");
    await assertAccessible(page, "media with resource listing");
    await card.getByRole("button", { name: "Take off the Resources page" }).click();
    await card.getByText("Resources page: not listed").waitFor();
    assert.deepEqual(sent.map((b) => [b.listed, b.category]), [[true, ""], [true, "brochure"], [false, "brochure"]]);
    await page.context().close();
  });
});
