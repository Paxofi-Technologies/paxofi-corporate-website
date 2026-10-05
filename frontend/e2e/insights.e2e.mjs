// News & Insights (D-021): the public list and article pages read the API on
// the server (a local stand-in here), and staff write articles in the staff
// area (the admin API is faked in the browser).
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const PORT = Number(process.env.E2E_PORT || 3123) + 10;
const API_PORT = PORT + 1;
const API = `http://127.0.0.1:${API_PORT}/api/v1`;
const IMAGE_ID = "a1a1a1a1-0000-4000-8000-000000000001";
const PNG = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=", "base64");

const ARTICLES = [
  { slug: "introducing-pif-2026", title: "Introducing PIF 2026", category: "announcement", category_label: "Announcement", summary: "Applications are open for the Paxofi Innovation Fellowship.", author_name: "Samuel Adeniji", published_at: "2026-10-05T09:00:00Z", updated_at: "2026-10-05T09:00:00Z", image: { path: `/api/v1/media/${IMAGE_ID}/launch.png`, alt: "The PIF 2026 launch", width: 1200, height: 630 } },
  { slug: "how-we-build-with-pcf", title: "How we build with Paxofi Core Framework", category: "insight", category_label: "Insight", summary: "Layered, testable PHP for long-lived systems.", author_name: null, published_at: "2026-10-01T09:00:00Z", updated_at: "2026-10-01T09:00:00Z", image: null },
];
const BODY = "Today we open **applications**.\n\n## Who can apply\n\n- Students\n- Career changers\n\nApply on [careers.paxofi.com](https://careers.paxofi.com). <script>window.hacked = true</script>";

let api;
before(async () => {
  api = createServer((request, response) => {
    const url = new URL(request.url, "http://localhost");
    const json = (status, body) => response.writeHead(status, { "content-type": "application/json" }).end(JSON.stringify(body));
    if (url.pathname.startsWith("/api/v1/media/")) return response.writeHead(200, { "content-type": "image/png", "cross-origin-resource-policy": "cross-origin" }).end(PNG);
    if (url.pathname === "/api/v1/articles") {
      const category = url.searchParams.get("category");
      const items = ARTICLES.filter((a) => !category || a.category === category);
      return json(200, { data: items, meta: { page: 1, per_page: 12, total: items.length, total_pages: 1 } });
    }
    const one = url.pathname.match(/^\/api\/v1\/articles\/([a-z0-9-]+)$/);
    if (one) {
      const article = ARTICLES.find((a) => a.slug === one[1]);
      return article ? json(200, { data: { ...article, body: BODY } }) : json(404, { success: false, error: { code: "NOT_FOUND", message: "Article not found." } });
    }
    return json(404, { success: false, error: { code: "NOT_FOUND", message: "Not found." } });
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(PORT, { apiBase: API });

describe("news and insights", () => {
  test("the list shows articles newest first, filters by category and is accessible", async () => {
    const page = await newPage();
    const response = await page.goto(`${BASE}/insights`);
    assert.equal(response.status(), 200);
    assert.deepEqual(await page.locator(".article-card h2").allTextContents(), ["Introducing PIF 2026", "How we build with Paxofi Core Framework"]);
    assert.ok(await page.getByRole("navigation", { name: "Main" }).getByRole("link", { name: "Insights" }).count(), "Insights is in the main menu");
    await assertAccessible(page, "insights list");
    await page.getByRole("link", { name: "Insight", exact: true }).click();
    await page.waitForURL(/category=insight/);
    assert.deepEqual(await page.locator(".article-card h2").allTextContents(), ["How we build with Paxofi Core Framework"]);
    await page.context().close();
  });

  test("an article renders its formatting safely, with article metadata", async () => {
    const page = await newPage();
    await page.goto(`${BASE}/insights/introducing-pif-2026`);
    assert.equal(await page.locator("h1").textContent(), "Introducing PIF 2026");
    assert.equal(await page.locator(".article-body h2").textContent(), "Who can apply");
    assert.equal(await page.locator(".article-body li").count(), 2);
    assert.equal(await page.locator(".article-body strong").textContent(), "applications");
    assert.equal(await page.locator('.article-body a[href="https://careers.paxofi.com"]').count(), 1);
    assert.ok(await page.getByText("<script>window.hacked = true</script>").isVisible(), "markup in an article is shown as text");
    assert.equal(await page.evaluate(() => window.hacked), undefined);
    assert.equal(await page.locator('meta[property="og:type"]').getAttribute("content"), "article");
    assert.match(await page.locator('meta[property="og:image"]').getAttribute("content"), /launch\.png$/);
    const blocks = (await page.locator('script[type="application/ld+json"]').allTextContents()).map((t) => JSON.parse(t));
    const jsonLd = blocks.find((b) => b.headline);
    assert.equal(jsonLd["@type"], "NewsArticle");
    assert.equal(jsonLd.author.name, "Samuel Adeniji");
    await assertAccessible(page, "article");
    assert.equal((await page.goto(`${BASE}/insights/no-such-article`)).status(), 404);
    const sitemap = await (await fetch(`${BASE}/sitemap.xml`)).text();
    assert.match(sitemap, /\/insights\/introducing-pif-2026/);
    await page.context().close();
  });

  test("staff write, preview and publish an article", async () => {
    const page = await newPage();
    const calls = [];
    const cors = { "Access-Control-Allow-Origin": BASE, "Access-Control-Allow-Credentials": "true", "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept", Vary: "Origin" };
    const reply = (route, status, body) => route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta: {} } : { success: false, error: body }) });
    let article = null;
    await page.route(`${API}/admin/**`, async (route) => {
      const request = route.request();
      if (request.method() === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
      const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
      const body = request.postData() ? request.postDataJSON() : null;
      calls.push(`${request.method()} ${path}`);
      if (path === "/session") return reply(route, 200, { id: "1", email: "ada@paxofi.com", display_name: "Ada Admin", status: "active", role: "administrator", role_label: "Administrator", permissions: ["content.edit", "content.publish"], two_factor_enabled: true });
      if (path === "/media") return reply(route, 200, [{ id: IMAGE_ID, kind: "image", filename: "launch.png", media_type: "image/png", format: "PNG", size_bytes: 100, width: 1200, height: 630, alt_text: "The PIF 2026 launch", title: null, path: `/api/v1/media/${IMAGE_ID}/launch.png`, uploaded_by: null, created_at: null, used_by: [] }]);
      if (path === "/articles" && request.method() === "POST") {
        if (body.summary.length < 20) return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { summary: "Write a summary of 20 to 300 characters. It shows on the list and in search results." } } });
        article = { id: "b2b2b2b2-0000-4000-8000-000000000002", slug: "our-first-insight", path: "/insights/our-first-insight", title: body.title, category: body.category, state: "draft", has_draft: true, published_at: null, updated_at: "2026-10-05 10:00:00", live: null, draft: { content: body, saved_at: "2026-10-05 10:00:00", author_name: "Ada Admin" } };
        return reply(route, 201, article);
      }
      if (path === `/articles/${article?.id}` && request.method() === "GET") return reply(route, 200, article);
      if (path === `/articles/${article?.id}/publish`) {
        article = { ...article, state: "published", has_draft: false, published_at: "2026-10-05 10:05:00", live: article.draft.content, draft: null };
        return reply(route, 200, article);
      }
      return reply(route, 404, { code: "NOT_FOUND", message: "Not found." });
    });

    await page.goto(`${BASE}/admin/content?tab=articles`);
    await page.getByRole("link", { name: "Write an article" }).click();
    await page.getByLabel("Title").fill("Our first insight");
    await page.getByLabel("Category").selectOption("insight");
    await page.getByLabel(/^Summary/).fill("Too short");
    await page.getByLabel("Picture (optional)").selectOption(IMAGE_ID);
    await page.getByLabel("Article").fill("First paragraph.\n\n## A heading\n\n- One point\n- Another point");
    await page.getByRole("button", { name: "Save draft" }).click();
    await page.getByText("Write a summary of 20 to 300 characters.").first().waitFor();
    await page.getByLabel(/^Summary/).fill("What we learned shipping our first products in 2026.");
    await page.getByRole("button", { name: "Save draft" }).click();
    await page.waitForURL(/\/admin\/content\/articles\/b2b2b2b2/);
    await page.getByText("Not published yet").waitFor();

    await page.getByRole("button", { name: "Preview" }).click();
    await page.getByRole("heading", { name: "A heading" }).waitFor();
    assert.equal(await page.locator(".article-preview li").count(), 2);
    await assertAccessible(page, "article editor preview");

    await page.getByRole("button", { name: "Publish", exact: true }).click();
    await page.getByText("Published. The article is on the website.").waitFor();
    assert.ok(await page.getByRole("link", { name: "View on the website" }).isVisible());
    assert.deepEqual(calls.filter((c) => c.startsWith("POST")), ["POST /articles", "POST /articles", "POST /articles/b2b2b2b2-0000-4000-8000-000000000002/publish"]);
    await page.context().close();
  });
});
