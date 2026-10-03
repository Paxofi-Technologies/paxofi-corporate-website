import { test } from "node:test";
import assert from "node:assert/strict";
import { FALLBACK_CATALOG, loadCatalog, parseCatalog } from "../lib/catalog.ts";

const ok = (body: unknown) => (async () => new Response(JSON.stringify(body), { status: 200 })) as unknown as typeof fetch;

test("uses the API's products in its order, with safe defaults", async () => {
  let url = "";
  const fake = (async (input: string) => {
    url = input;
    return new Response(JSON.stringify({ data: [{ slug: "pay", name: "Paxofi Pay Plus", label: "", icon: "unknown", summary: "New wording.", points: ["One", 2, ""] }] }));
  }) as unknown as typeof fetch;

  const items = await loadCatalog("products", "https://api.example/api/v1/", fake);

  assert.equal(url, "https://api.example/api/v1/products?per_page=50");
  assert.deepEqual(items, [{ slug: "pay", name: "Paxofi Pay Plus", label: null, icon: "layers", summary: "New wording.", points: ["One"] }]);
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
