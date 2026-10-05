import { test } from "node:test";
import assert from "node:assert/strict";
import { isSafeArticleLink, loadArticle, loadArticles, parseBody, parseInline, readingMinutes } from "../lib/articles.ts";

test("article bodies become headings, paragraphs and lists", () => {
  const blocks = parseBody("Intro line one\nline two.\n\n## Who can apply\n\n- Students\n- Career changers\n\n1. Apply\n2. Interview\nAfter list.");
  assert.deepEqual(blocks.map((b) => b.type), ["paragraph", "heading", "list", "list", "paragraph"]);
  assert.deepEqual(blocks[0], { type: "paragraph", content: [{ type: "text", text: "Intro line one line two." }] });
  assert.equal(blocks[2].type === "list" && blocks[2].ordered, false);
  assert.equal(blocks[3].type === "list" && blocks[3].ordered, true);
});

test("only bold and safe links are formatting; everything else stays text", () => {
  assert.deepEqual(parseInline("Read **this** on [our site](/careers)."), [
    { type: "text", text: "Read " }, { type: "bold", text: "this" }, { type: "text", text: " on " }, { type: "link", text: "our site", href: "/careers" }, { type: "text", text: "." },
  ]);
  assert.deepEqual(parseInline("[x](javascript:alert(1))")[0].type, "text");
  assert.equal(isSafeArticleLink("//evil.example"), false);
  assert.equal(isSafeArticleLink("mailto:hr@paxofi.com"), true);
  assert.deepEqual(parseInline("<script>alert(1)</script>"), [{ type: "text", text: "<script>alert(1)</script>" }], "markup is just text; React escapes it");
});

test("reading time and API reads", async () => {
  assert.equal(readingMinutes("word ".repeat(660)), 3);
  const ok = (body: unknown, status = 200) => (async () => new Response(JSON.stringify(body), { status })) as typeof fetch;
  const row = { slug: "a", title: "A", category: "news", category_label: "News", summary: "S", published_at: "2026-10-05T10:00:00Z", image: { path: "/api/v1/media/a1a1a1a1-0000-4000-8000-000000000001/x.jpg", alt: "Alt", width: 1200, height: 630 } };
  const page = await loadArticles("https://api.paxofi.com/api/v1", {}, ok({ data: [row, { bad: 1 }], meta: { page: 1, total_pages: 2 } }));
  assert.equal(page?.items.length, 1);
  assert.equal(page?.items[0].image?.src, "https://api.paxofi.com/api/v1/media/a1a1a1a1-0000-4000-8000-000000000001/x.jpg");
  assert.equal(page?.totalPages, 2);
  assert.equal(await loadArticles("https://api.paxofi.com/api/v1", {}, ok({}, 500)), null);
  assert.equal(await loadArticle("https://api.paxofi.com/api/v1", "a", ok({}, 404)), "missing");
  assert.equal(await loadArticle("https://api.paxofi.com/api/v1", "../x", ok({})), "missing");
  assert.equal((await loadArticle("https://api.paxofi.com/api/v1", "a", ok({ data: { ...row, body: "Hello" } })) as { body: string }).body, "Hello");
});
