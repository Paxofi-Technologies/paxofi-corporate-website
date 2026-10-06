/**
 * The Resources page (decision D-023): documents an administrator lists from the
 * media library, grouped by category. Framework-free for unit tests.
 */
import { describeDownload, mediaUrl } from "./catalog";

/** Display order and labels; backend MediaLibrary::RESOURCE_CATEGORIES keeps the same list. */
export const RESOURCE_CATEGORIES = {
  brochure: "Brochures",
  guide: "Guides",
  whitepaper: "Whitepapers",
  policy: "Policies",
  other: "Other documents",
} as const;
export type ResourceCategory = keyof typeof RESOURCE_CATEGORIES;

export type Resource = {
  title: string;
  summary: string;
  category: ResourceCategory;
  /** Absolute download URL on the API host. */
  href: string;
  /** "PDF, 1.2 MB". */
  meta: string;
  listedAt: string;
};

const TIMEOUT_MS = 1500;

export function isResourceCategory(value: unknown): value is ResourceCategory {
  return typeof value === "string" && Object.prototype.hasOwnProperty.call(RESOURCE_CATEGORIES, value);
}

/** Validates the API response; null when it is not a usable list. Items without a safe download link are left out. */
export function parseResources(payload: unknown, apiBase: string): Resource[] | null {
  const data = isRecord(payload) && Array.isArray(payload.data) ? payload.data : null;
  if (!data) return null;
  const items: Resource[] = [];
  for (const raw of data) {
    if (!isRecord(raw) || typeof raw.title !== "string" || raw.title === "" || !isResourceCategory(raw.category)) continue;
    const href = mediaUrl(apiBase, raw.path);
    if (!href) continue;
    items.push({
      title: raw.title,
      summary: typeof raw.summary === "string" ? raw.summary : "",
      category: raw.category,
      href,
      meta: describeDownload(typeof raw.format === "string" ? raw.format : "File", Number(raw.size_bytes) || 0),
      listedAt: typeof raw.listed_at === "string" ? raw.listed_at : "",
    });
  }
  return items;
}

/** Listed documents from the API, or null if it cannot be read (the page then says so). */
export async function loadResources(apiBase: string | undefined, fetchImpl: typeof fetch = fetch): Promise<Resource[] | null> {
  const base = (apiBase ?? "").trim().replace(/\/+$/, "");
  if (!/^https?:\/\//.test(base)) return null;
  try {
    const response = await fetchImpl(`${base}/resources`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (!response.ok) return unavailable(`HTTP ${response.status}`);
    return parseResources(await response.json(), base) ?? unavailable("unexpected response");
  } catch (error) {
    return unavailable(error instanceof Error ? error.message : "request failed");
  }
}

/** The categories that have documents, in display order. */
export function groupResources(items: Resource[]): { category: ResourceCategory; label: string; items: Resource[] }[] {
  return (Object.keys(RESOURCE_CATEGORIES) as ResourceCategory[])
    .map((category) => ({ category, label: RESOURCE_CATEGORIES[category], items: items.filter((i) => i.category === category) }))
    .filter((group) => group.items.length > 0);
}

function unavailable(reason: string): null {
  if (process.env.NODE_ENV === "production") console.warn(`resources: could not load (${reason})`);
  return null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
