/**
 * Page text edited in the staff area (decision D-015). Framework-free for unit
 * tests.
 *
 * page-copy.json lists every editable field with its built-in wording; the API
 * keeps an identical copy (backend/config/page-copy.json, checked by a test).
 * A page shows its published text where there is some, and the built-in
 * wording everywhere else, including whenever the API cannot be read.
 */
import schema from "./page-copy.json";

export type PageKey = "home" | "about" | "services" | "products" | "careers" | "contact";
export type PageField = { key: string; group: string; label: string; kind: "line" | "text"; max: number; default: string };
export type PageCopy = Record<string, string>;

export const PAGE_COPY = schema.pages as Record<PageKey, { label: string; path: string; fields: PageField[] }>;

const TIMEOUT_MS = 1500;

/** The built-in wording of a page. */
export function defaultCopy(page: PageKey): PageCopy {
  return Object.fromEntries(PAGE_COPY[page].fields.map((field) => [field.key, field.default]));
}

/** Published text over the built-in wording; anything missing, empty or too long keeps the built-in text. */
export function mergeCopy(page: PageKey, published: unknown): PageCopy {
  const copy = defaultCopy(page);
  if (!isRecord(published)) return copy;
  for (const field of PAGE_COPY[page].fields) {
    const value = published[field.key];
    if (typeof value === "string" && value.trim() !== "" && value.length <= field.max) copy[field.key] = value;
  }
  return copy;
}

/** A page's text from the API, or its built-in wording if the API cannot be read in time. */
export async function loadPageCopy(page: PageKey, apiBase: string | undefined, fetchImpl: typeof fetch = fetch): Promise<PageCopy> {
  const base = (apiBase ?? "").trim().replace(/\/+$/, "");
  if (!/^https?:\/\//.test(base)) return builtIn(page, "API_BASE_URL is not an absolute URL");
  try {
    const response = await fetchImpl(`${base}/pages/${page}`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: AbortSignal.timeout(TIMEOUT_MS),
    });
    if (!response.ok) return builtIn(page, `HTTP ${response.status}`);
    const payload: unknown = await response.json();
    const data = isRecord(payload) && isRecord(payload.data) ? payload.data : null;
    if (!data) return builtIn(page, "unexpected response");
    return mergeCopy(page, data.fields);
  } catch (error) {
    return builtIn(page, error instanceof Error ? error.message : "request failed");
  }
}

/** Built-in wording, with one line in the server log (cPanel: stderr.log) saying why. */
function builtIn(page: PageKey, reason: string): PageCopy {
  if (process.env.NODE_ENV === "production") console.warn(`page-copy: showing built-in ${page} (${reason})`);
  return defaultCopy(page);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
