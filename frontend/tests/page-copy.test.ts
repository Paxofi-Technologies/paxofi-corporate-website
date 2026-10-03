import { test } from "node:test";
import assert from "node:assert/strict";
import { PAGE_COPY, defaultCopy, loadPageCopy, mergeCopy } from "../lib/page-copy.ts";

const ok = (body: unknown) => (async () => new Response(JSON.stringify(body), { status: 200 })) as unknown as typeof fetch;

test("published text replaces the built-in wording field by field", async () => {
  let url = "";
  const fake = (async (input: string) => {
    url = input;
    return new Response(JSON.stringify({ data: { page: "home", fields: { hero_lead: "New lead text." } } }));
  }) as unknown as typeof fetch;

  const copy = await loadPageCopy("home", "https://api.example/api/v1/", fake);

  assert.equal(url, "https://api.example/api/v1/pages/home");
  assert.equal(copy.hero_lead, "New lead text.");
  assert.equal(copy.hero_eyebrow, defaultCopy("home").hero_eyebrow, "fields not published keep the built-in wording");
});

test("empty, too long, unknown or non-text values keep the built-in wording", () => {
  const field = PAGE_COPY.about.fields.find((f) => f.key === "hero_title")!;
  const copy = mergeCopy("about", { hero_title: "x".repeat(field.max + 1), hero_intro: "  ", mission_title: 7, nonsense: "ignored" });
  assert.deepEqual(copy, defaultCopy("about"));
});

test("falls back to the built-in wording when the API cannot be read", async () => {
  const failing = (async () => {
    throw new TypeError("fetch failed");
  }) as unknown as typeof fetch;
  const serverError = (async () => new Response("oops", { status: 500 })) as unknown as typeof fetch;

  assert.deepEqual(await loadPageCopy("contact", "https://api.example/api/v1", failing), defaultCopy("contact"));
  assert.deepEqual(await loadPageCopy("contact", "https://api.example/api/v1", serverError), defaultCopy("contact"));
  assert.deepEqual(await loadPageCopy("contact", "https://api.example/api/v1", ok({ data: [] })), defaultCopy("contact"));
  assert.deepEqual(await loadPageCopy("contact", undefined, ok({ data: { fields: { signoff: "Hi" } } })), defaultCopy("contact"), "no absolute API URL");
});

test("every field has a unique key and a default within its limit", () => {
  for (const [page, { fields }] of Object.entries(PAGE_COPY)) {
    assert.equal(new Set(fields.map((f) => f.key)).size, fields.length, `${page} keys are unique`);
    for (const f of fields) assert.ok(f.default.trim() !== "" && f.default.length <= f.max, `${page}.${f.key} default fits`);
    assert.ok(fields.some((f) => f.key === "meta_description"), `${page} has a search description`);
  }
});
