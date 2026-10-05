/**
 * News & Insights (decision D-021): articles from the API and the small text
 * format their bodies use. Framework-free so it can be unit tested.
 *
 * Body format (the staff area shows the same help):
 *   blank line         new paragraph
 *   ## Heading         section heading (### for a smaller one)
 *   - item             bulleted list (1. item for a numbered list)
 *   **bold**           bold text
 *   [text](https://…)  link (https, http, mailto or a page on this site)
 * Everything is plain text; the website never inserts HTML from an article.
 */
import { mediaUrl } from "./catalog";

export type ArticleCategory = "news" | "insight" | "announcement";
export const ARTICLE_CATEGORIES: { value: ArticleCategory; label: string }[] = [
  { value: "news", label: "News" },
  { value: "insight", label: "Insight" },
  { value: "announcement", label: "Announcement" },
];

export type ArticleImage = { src: string; alt: string; width: number; height: number };
export type ArticleSummary = {
  slug: string;
  title: string;
  category: ArticleCategory;
  categoryLabel: string;
  summary: string;
  authorName: string | null;
  publishedAt: string;
  updatedAt: string;
  image: ArticleImage | null;
};
export type Article = ArticleSummary & { body: string };

export type Inline = { type: "text"; text: string } | { type: "bold"; text: string } | { type: "link"; text: string; href: string };
export type Block =
  | { type: "heading"; level: 2 | 3; content: Inline[] }
  | { type: "paragraph"; content: Inline[] }
  | { type: "list"; ordered: boolean; items: Inline[][] };

/** Same rule as the API (ArticleContent::isSafeLink). */
export function isSafeArticleLink(url: string): boolean {
  return /^(https?:\/\/[^\s"<>]+|mailto:[^\s"<>@]+@[^\s"<>]+|\/[^\s"<>]*)$/i.test(url) && !url.startsWith("//");
}

/** **bold** and [text](url) inside one line; anything else stays text. */
export function parseInline(line: string): Inline[] {
  const out: Inline[] = [];
  const pattern = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;
  let last = 0;
  for (let match = pattern.exec(line); match; match = pattern.exec(line)) {
    if (match.index > last) out.push({ type: "text", text: line.slice(last, match.index) });
    if (match[1] !== undefined) out.push({ type: "bold", text: match[1] });
    else if (isSafeArticleLink(match[3])) out.push({ type: "link", text: match[2], href: match[3] });
    else out.push({ type: "text", text: match[0] });
    last = match.index + match[0].length;
  }
  if (last < line.length) out.push({ type: "text", text: line.slice(last) });
  return out;
}

/** Splits an article body into headings, paragraphs and lists. */
export function parseBody(body: string): Block[] {
  const blocks: Block[] = [];
  const lines = body.replace(/\r\n?/g, "\n").split("\n");
  let paragraph: string[] = [];
  let list: { ordered: boolean; items: Inline[][] } | null = null;
  const flush = () => {
    if (paragraph.length) blocks.push({ type: "paragraph", content: parseInline(paragraph.join(" ")) });
    paragraph = [];
    if (list) blocks.push({ type: "list", ...list });
    list = null;
  };
  for (const raw of lines) {
    const line = raw.trim();
    const heading = /^(#{2,3})\s+(.+)$/.exec(line);
    const bullet = /^[-*]\s+(.+)$/.exec(line);
    const numbered = /^\d{1,3}[.)]\s+(.+)$/.exec(line);
    if (line === "") {
      flush();
    } else if (heading) {
      flush();
      blocks.push({ type: "heading", level: heading[1].length === 2 ? 2 : 3, content: parseInline(heading[2]) });
    } else if (bullet || numbered) {
      const ordered = Boolean(numbered);
      if (paragraph.length || (list && list.ordered !== ordered)) flush();
      list ??= { ordered, items: [] };
      list.items.push(parseInline((bullet ?? numbered)![1]));
    } else {
      if (list) flush();
      paragraph.push(line);
    }
  }
  flush();
  return blocks;
}

/** Minutes to read at about 220 words a minute. */
export function readingMinutes(body: string): number {
  return Math.max(1, Math.round(body.split(/\s+/).filter(Boolean).length / 220));
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function parseSummary(raw: unknown, apiBase: string | undefined): ArticleSummary | null {
  if (!isRecord(raw) || typeof raw.slug !== "string" || typeof raw.title !== "string" || typeof raw.published_at !== "string") return null;
  const category = ARTICLE_CATEGORIES.find((c) => c.value === raw.category)?.value ?? "insight";
  let image: ArticleImage | null = null;
  if (isRecord(raw.image)) {
    const src = mediaUrl(apiBase, raw.image.path);
    const width = Number(raw.image.width);
    const height = Number(raw.image.height);
    if (src && width > 0 && height > 0) image = { src, alt: typeof raw.image.alt === "string" ? raw.image.alt : "", width, height };
  }
  return {
    slug: raw.slug,
    title: raw.title,
    category,
    categoryLabel: typeof raw.category_label === "string" ? raw.category_label : "Insight",
    summary: typeof raw.summary === "string" ? raw.summary : "",
    authorName: typeof raw.author_name === "string" ? raw.author_name : null,
    publishedAt: raw.published_at,
    updatedAt: typeof raw.updated_at === "string" ? raw.updated_at : raw.published_at,
    image,
  };
}

const TIMEOUT_MS = 2500;
function root(apiBase: string | undefined): string {
  return (apiBase ?? "").trim().replace(/\/+$/, "") || "/api/v1";
}

export type ArticlePage = { items: ArticleSummary[]; page: number; totalPages: number };

/** Published articles, or null when the API cannot be read. */
export async function loadArticles(apiBase: string | undefined, options: { page?: number; category?: string | null; perPage?: number } = {}, fetchImpl: typeof fetch = fetch): Promise<ArticlePage | null> {
  const query = new URLSearchParams({ page: String(options.page ?? 1), per_page: String(options.perPage ?? 12) });
  if (options.category && ARTICLE_CATEGORIES.some((c) => c.value === options.category)) query.set("category", options.category);
  try {
    const response = await fetchImpl(`${root(apiBase)}/articles?${query}`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (!response.ok) return null;
    const payload: unknown = await response.json();
    if (!isRecord(payload) || !Array.isArray(payload.data)) return null;
    const meta = isRecord(payload.meta) ? payload.meta : {};
    return {
      items: payload.data.map((row) => parseSummary(row, apiBase)).filter((a): a is ArticleSummary => a !== null),
      page: Number(meta.page) || 1,
      totalPages: Number(meta.total_pages) || 1,
    };
  } catch {
    return null;
  }
}

/** One published article; "missing" when it is not published, null when the API cannot be read. */
export async function loadArticle(apiBase: string | undefined, slug: string, fetchImpl: typeof fetch = fetch): Promise<Article | "missing" | null> {
  if (!/^[a-z0-9-]{1,170}$/.test(slug)) return "missing";
  try {
    const response = await fetchImpl(`${root(apiBase)}/articles/${slug}`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (response.status === 404) return "missing";
    if (!response.ok) return null;
    const payload: unknown = await response.json();
    const data = isRecord(payload) ? payload.data : null;
    const summary = parseSummary(data, apiBase);
    return summary && isRecord(data) && typeof data.body === "string" ? { ...summary, body: data.body } : null;
  } catch {
    return null;
  }
}

export function formatArticleDate(iso: string): string {
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? iso : new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "long", year: "numeric", timeZone: "Africa/Lagos" }).format(date);
}
