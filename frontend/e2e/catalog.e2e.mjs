// Products and services pages read from the API at request time (D-011) and
// fall back to the built-in copy when it fails. A small local HTTP server
// stands in for the API that the website server calls.
import { after, before, describe, test } from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { assertAccessible, startHarness } from "./harness.mjs";

const API_PORT = Number(process.env.E2E_PORT || 3123) + 3;
const mode = { value: "ok" };

const IMAGE_ID = "c0ffee00-0000-4000-8000-000000000002";
const DOC_ID = "c0ffee00-0000-4000-8000-000000000001";
// A 1×1 PNG served by the stand-in API host.
const PNG = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=", "base64");

const PRODUCTS = [
  {
    slug: "paxofi-pay", name: "Paxofi Pay Plus", label: "Paxofi Product", icon: "shield-check", summary: "Edited in the staff area.", points: ["Instant settlement", "Clear fees"], sort_order: 10,
    image: { path: `/api/v1/media/${IMAGE_ID}/pay.png`, alt: "Paxofi Pay on a phone", width: 1200, height: 800 },
    document: { path: `/api/v1/media/${DOC_ID}/Paxofi-Pay-brochure.pdf`, title: "Paxofi Pay brochure", format: "PDF", size_bytes: 1258291 },
  },
  { slug: "paxofi-core-framework", name: "Paxofi Core Framework", label: "Paxofi Technology", icon: "cog", summary: "An independent PHP application framework.", points: [], sort_order: 20 },
];
const SERVICES = [
  { slug: "data-ai", name: "Data & AI Engineering", label: null, icon: "database", summary: "Data platforms and practical AI features.", points: [], sort_order: 5 },
  { slug: "software-web-engineering", name: "Software & Web Engineering", label: null, icon: "code", summary: "Web platforms and business applications.", points: [], sort_order: 10 },
];

let api;
before(async () => {
  api = createServer((request, response) => {
    if (mode.value === "down") {
      response.writeHead(500).end("down");
      return;
    }
    const path = new URL(request.url, "http://localhost").pathname;
    if (path.startsWith("/api/v1/media/")) {
      // As the API serves media: embeddable by the website, which requires CORP (COEP require-corp).
      response.writeHead(200, { "content-type": path.endsWith(".png") ? "image/png" : "application/pdf", "cross-origin-resource-policy": "cross-origin" }).end(PNG);
      return;
    }
    const data = path === "/api/v1/products" ? PRODUCTS : path === "/api/v1/services" ? SERVICES : null;
    if (!data) {
      response.writeHead(404).end();
      return;
    }
    response.writeHead(200, { "content-type": "application/json" }).end(JSON.stringify({ success: true, data, meta: {} }));
  });
  await new Promise((resolve) => api.listen(API_PORT, "127.0.0.1", resolve));
});
after(() => api?.close());

const { base: BASE, newPage } = startHarness(Number(process.env.E2E_PORT || 3123) + 2, { apiBase: `http://127.0.0.1:${API_PORT}/api/v1` });

describe("catalogue pages", () => {
  test("products come from the API, in its order, with their points", async () => {
    mode.value = "ok";
    const page = await newPage();
    await page.goto(`${BASE}/products`);
    const names = await page.locator(".product-card h2").allTextContents();
    assert.deepEqual(names, ["Paxofi Pay Plus", "Paxofi Core Framework"]);
    await page.getByText("Edited in the staff area.").waitFor();
    assert.deepEqual(await page.locator(".product-card").first().locator(".check-list li").allTextContents(), ["Instant settlement", "Clear fees"]);
    const picture = page.getByRole("img", { name: "Paxofi Pay on a phone" });
    await picture.scrollIntoViewIfNeeded();
    assert.equal(await picture.getAttribute("src"), `http://127.0.0.1:${API_PORT}/api/v1/media/${IMAGE_ID}/pay.png`);
    await page.waitForFunction(() => document.querySelector(".card-image")?.complete);
    assert.ok(await picture.evaluate((img) => img.naturalWidth > 0), "the picture loads from the API host (CSP img-src)");
    const download = page.getByRole("link", { name: "Paxofi Pay brochure (PDF, 1.2 MB)" });
    assert.equal(await download.getAttribute("href"), `http://127.0.0.1:${API_PORT}/api/v1/media/${DOC_ID}/Paxofi-Pay-brochure.pdf`);
    await assertAccessible(page, "on products from the API");

    await page.goto(BASE);
    await page.getByRole("link", { name: "More about Paxofi Pay Plus" }).waitFor();
    await page.context().close();
  });

  test("services come from the API", async () => {
    mode.value = "ok";
    const page = await newPage();
    await page.goto(`${BASE}/services`);
    assert.deepEqual(await page.locator(".icon-card h3").allTextContents(), ["Data & AI Engineering", "Software & Web Engineering"]);
    await assertAccessible(page, "on services from the API");
    await page.context().close();
  });

  test("when the API fails, the pages show the built-in copy", async () => {
    mode.value = "down";
    const page = await newPage();
    const response = await page.goto(`${BASE}/products`);
    assert.equal(response.status(), 200);
    assert.deepEqual(await page.locator(".product-card h2").allTextContents(), ["Paxofi Pay", "Paxofi Core Framework"]);
    await page.goto(`${BASE}/services`);
    assert.equal(await page.locator(".icon-card h3").count(), 6);
    await page.context().close();
    mode.value = "ok";
  });
});
