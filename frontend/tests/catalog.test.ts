import { test } from "node:test";
import assert from "node:assert/strict";
import { FALLBACK_CATALOG, describeDownload, loadCatalog, mediaUrl, parseCatalog } from "../lib/catalog.ts";

const ok = (body: unknown) => (async () => new Response(JSON.stringify(body), { status: 200 })) as unknown as typeof fetch;

test("uses the API's products in its order, with safe defaults", async () => {
  let url = "";
  const fake = (async (input: string) => {
    url = input;
    return new Response(JSON.stringify({ data: [{ slug: "pay", name: "Paxofi Pay Plus", label: "", icon: "unknown", summary: "New wording.", points: ["One", 2, ""] }] }));
  }) as unknown as typeof fetch;

  const items = await loadCatalog("products", "https://api.example/api/v1/", fake);

  assert.equal(url, "https://api.example/api/v1/products?per_page=50");
  assert.deepEqual(items, [{ slug: "pay", name: "Paxofi Pay Plus", label: null, icon: "layers", summary: "New wording.", points: ["One"], image: null, document: null }]);
});

test("falls back to the built-in copy when the API cannot be read", async () => {
  const failing = (async () => {
    throw new TypeError("fetch failed");
  }) as unknown as typeof fetch;
  const serverError = (async () => new Response("oops", { status: 500 })) as unknown as typeof fetch;

  assert.equal(await loadCatalog("services", "https://api.example/api/v1", failing), FALLBACK_CATALOG.services);
  assert.equal(await loadCatalog("services", "https://api.example/api/v1", serverError), FALLBACK_CATALOG.services);
  assert.equal(await loadCatalog("services", "https://api.example/api/v1", ok({ unexpected: true })), FALLBACK_CATALOG.services);
  assert.equal(await loadCatalog("products", undefined, ok({ data: [] })), FALLBACK_CATALOG.products, "no absolute API URL");
});

test("an empty published list is respected (everything hidden)", async () => {
  assert.deepEqual(await loadCatalog("products", "https://api.example/api/v1", ok({ data: [] })), []);
});

test("the built-in copy matches the V1 site", () => {
  assert.equal(FALLBACK_CATALOG.products.length, 2);
  assert.equal(FALLBACK_CATALOG.services.length, 6);
  assert.deepEqual(parseCatalog({ data: [{ name: "", summary: "x" }] }), []);
});

test("pictures and documents link to the API host, and nothing else (D-012)", () => {
  const id = "0f8b1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c2d";
  const [item] = parseCatalog(
    {
      data: [
        {
          name: "Paxofi Pay",
          summary: "Payments.",
          image: { path: `/api/v1/media/${id}/pay.png`, alt: "Paxofi Pay on a phone", width: 800, height: 600 },
          document: { path: `/api/v1/media/${id}/brochure.pdf`, title: "Brochure", format: "PDF", size_bytes: 1258291 },
        },
      ],
    },
    "https://api.paxofi.com/api/v1",
  )!;
  assert.deepEqual(item.image, { src: `https://api.paxofi.com/api/v1/media/${id}/pay.png`, alt: "Paxofi Pay on a phone", width: 800, height: 600 });
  assert.deepEqual(item.document, { href: `https://api.paxofi.com/api/v1/media/${id}/brochure.pdf`, title: "Brochure", format: "PDF", sizeBytes: 1258291 });

  assert.equal(mediaUrl("https://api.paxofi.com/api/v1", "https://evil.example/x.png"), null);
  assert.equal(mediaUrl("https://api.paxofi.com/api/v1", "javascript:alert(1)"), null);
  assert.equal(mediaUrl("https://api.paxofi.com/api/v1", `/api/v1/media/${id}/../../x`), null);
  assert.equal(mediaUrl(undefined, `/api/v1/media/${id}/a.png`), null);
  assert.equal(parseCatalog({ data: [{ name: "X", summary: "Y", image: { path: "/elsewhere.png", alt: "a", width: 1, height: 1 } }] }, "https://api.paxofi.com")![0].image, null);

  assert.equal(describeDownload("PDF", 1258291), "PDF, 1.2 MB");
  assert.equal(describeDownload("Word", 2 * 1024 * 1024), "Word, 2 MB");
  assert.equal(describeDownload("CSV", 300), "CSV, 1 KB");
});
