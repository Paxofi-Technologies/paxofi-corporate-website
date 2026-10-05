import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { FALLBACK_CATALOG, PRODUCT_STATUSES, describeDownload, loadCatalog, mediaUrl, paragraphs, parseCatalog, relatedHref } from "../lib/catalog.ts";

const ok = (body: unknown) => (async () => new Response(JSON.stringify(body), { status: 200 })) as unknown as typeof fetch;

test("uses the API's products in its order, with safe defaults", async () => {
  let url = "";
  const fake = (async (input: string) => {
    url = input;
    return new Response(JSON.stringify({ data: [{ slug: "pay", name: "Paxofi Pay Plus", label: "", icon: "unknown", summary: "New wording.", points: ["One", 2, ""] }] }));
  }) as unknown as typeof fetch;

  const items = await loadCatalog("products", "https://api.example/api/v1/", fake);

  assert.equal(url, "https://api.example/api/v1/products?per_page=50");
  assert.deepEqual(items, [{ slug: "pay", name: "Paxofi Pay Plus", label: null, icon: "layers", summary: "New wording.", points: ["One"], image: null, document: null, status: null, description: null, related: [] }]);
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
  assert.deepEqual(FALLBACK_CATALOG.products.map((p) => [p.name, p.status]), [["Paxofi Pay", "planned"], ["PaxofiCloud", "in_development"], ["Paxofi Core Framework", "available"]]);
  assert.equal(FALLBACK_CATALOG.services.length, 6);
  assert.equal(FALLBACK_CATALOG.industries.length, 10);
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

test("products carry a status label; unknown statuses are dropped (D-022)", () => {
  const items = parseCatalog({ data: [{ name: "A", summary: "x", status: "beta" }, { name: "B", summary: "x", status: "launched" }] });
  assert.deepEqual(items?.map((i) => i.status), ["beta", null]);
  assert.equal(PRODUCT_STATUSES.in_development, "In development");
});

test("industries keep their description and only well-formed related items (D-022)", () => {
  const [industry] = parseCatalog({
    data: [
      {
        slug: "education",
        name: "Education",
        summary: "Learning platforms.",
        description: "First.\n\n  Second.  \n\n\n",
        related: [
          { type: "service", slug: "software-web-engineering", name: "Software & Web Engineering", summary: "Web platforms.", icon: "code", status: null },
          { type: "product", slug: "paxofi-pay", name: "Paxofi Pay", summary: "Payments.", icon: "skull", status: "planned" },
          { type: "partner", slug: "x", name: "X" },
          { type: "service", slug: "../admin", name: "Bad" },
          { type: "service", slug: "no-name", name: "" },
        ],
      },
    ],
  })!;
  assert.deepEqual(paragraphs(industry.description), ["First.", "Second."]);
  assert.deepEqual(industry.related?.map((r) => [r.type, r.slug, r.icon, r.status]), [["service", "software-web-engineering", "code", null], ["product", "paxofi-pay", "layers", "planned"]]);
  assert.equal(relatedHref(industry.related![0]), "/services#software-web-engineering");
  assert.equal(relatedHref(industry.related![1]), "/products#paxofi-pay");
});

test("the built-in industries match migration 018 and link to built-in products and services", () => {
  const sql = readFileSync(new URL("../../database/018_industries_and_product_status.sql", import.meta.url), "utf8");
  for (const industry of FALLBACK_CATALOG.industries) {
    assert.ok(sql.includes(`'${industry.slug}', '${industry.name}', '${industry.icon}'`), industry.slug);
    assert.ok(sql.includes(`'${industry.summary}'`), `${industry.slug} summary`);
    assert.ok(sql.includes(`'${industry.description!.replace(/\n/g, "\\n")}'`), `${industry.slug} description`);
    assert.ok(industry.related!.length >= 3, `${industry.slug} links its related items`);
  }
  assert.deepEqual(FALLBACK_CATALOG.industries.map((i) => i.slug), ["financial-services", "education", "healthcare", "retail-commerce", "logistics-transportation", "government", "agriculture", "manufacturing", "non-profit", "startups-smes"]);
});
