import { test } from "node:test";
import assert from "node:assert/strict";
import { groupResources, loadResources, parseResources } from "../lib/resources.ts";

const API = "https://api.paxofi.com/api/v1";
const ID = "e0e0e0e0-0000-4000-8000-000000000001";
const ok = (body: unknown) => (async () => new Response(JSON.stringify(body), { status: 200 })) as unknown as typeof fetch;

test("listed documents link to the API host with their format and size (D-023)", () => {
  const items = parseResources(
    {
      data: [
        { title: "Paxofi Pay brochure", summary: "How payments stay certain.", category: "brochure", format: "PDF", size_bytes: 1258291, path: `/api/v1/media/${ID}/brochure.pdf`, listed_at: "2026-10-06T09:00:00Z" },
        { title: "Off-site", summary: "x", category: "guide", format: "PDF", size_bytes: 1, path: "https://evil.example/file.pdf" },
        { title: "Unknown category", summary: "x", category: "secret", format: "PDF", size_bytes: 1, path: `/api/v1/media/${ID}/x.pdf` },
        { title: "", category: "guide", path: `/api/v1/media/${ID}/y.pdf` },
      ],
    },
    API,
  );
  assert.deepEqual(items, [
    { title: "Paxofi Pay brochure", summary: "How payments stay certain.", category: "brochure", href: `https://api.paxofi.com/api/v1/media/${ID}/brochure.pdf`, meta: "PDF, 1.2 MB", listedAt: "2026-10-06T09:00:00Z" },
  ]);
  assert.equal(parseResources({ nope: true }, API), null);
});

test("groups follow the fixed category order and skip empty ones", () => {
  const base = { summary: "", href: "https://api.paxofi.com/x", meta: "PDF, 1 KB", listedAt: "" };
  const groups = groupResources([
    { ...base, title: "B", category: "other" },
    { ...base, title: "A", category: "guide" },
    { ...base, title: "C", category: "guide" },
  ]);
  assert.deepEqual(groups.map((g) => [g.label, g.items.map((i) => i.title)]), [["Guides", ["A", "C"]], ["Other documents", ["B"]]]);
});

test("a failing API gives null so the page can say so; an empty list is respected", async () => {
  const failing = (async () => {
    throw new TypeError("fetch failed");
  }) as unknown as typeof fetch;
  assert.equal(await loadResources(API, failing), null);
  assert.equal(await loadResources(API, (async () => new Response("x", { status: 500 })) as unknown as typeof fetch), null);
  assert.equal(await loadResources(undefined, ok({ data: [] })), null);
  assert.deepEqual(await loadResources(API, ok({ data: [] })), []);
});
