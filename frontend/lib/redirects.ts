/**
 * Redirects for retired or renamed pages (D-025). The proxy reads the list
 * from the API at most once a minute and answers a matching address with a
 * permanent redirect, so old links and search results keep working. If the
 * API cannot be read, the last list is kept (or none), and pages work as usual.
 */

export type RedirectMap = Map<string, string>;

const TTL_MS = 60_000;
const TIMEOUT_MS = 1_500;

/** The form addresses are stored in: lower-case, no trailing slash. */
export function normalizePath(pathname: string): string {
  let path = pathname.toLowerCase();
  if (path.length > 1) path = path.replace(/\/+$/, "");
  return path || "/";
}

export function parseRedirects(payload: unknown): RedirectMap {
  const map: RedirectMap = new Map();
  const data = typeof payload === "object" && payload !== null ? (payload as { data?: unknown }).data : null;
  if (!Array.isArray(data)) return map;
  for (const entry of data) {
    if (typeof entry !== "object" || entry === null) continue;
    const { from, to } = entry as { from?: unknown; to?: unknown };
    if (typeof from !== "string" || typeof to !== "string" || !from.startsWith("/")) continue;
    if (!(to.startsWith("/") && !to.startsWith("//")) && !/^https:\/\//i.test(to)) continue;
    map.set(normalizePath(from), to);
  }
  return map;
}

/**
 * Where a request should go instead, or null. A site target keeps the
 * visitor's query string unless the target sets its own.
 */
export function redirectTarget(map: RedirectMap, pathname: string, search: string): string | null {
  if (map.size === 0) return null;
  const to = map.get(normalizePath(pathname));
  if (!to) return null;
  if (to.startsWith("/") && search && !to.includes("?")) {
    const hash = to.indexOf("#");
    return hash === -1 ? to + search : to.slice(0, hash) + search + to.slice(hash);
  }
  return to;
}

let cached: { map: RedirectMap; until: number } | null = null;
let pending: Promise<RedirectMap> | null = null;

/** The current list, refreshed from `${apiBase}/redirects` when older than a minute. */
export async function loadRedirects(apiBase: string, now = Date.now(), fetchImpl: typeof fetch = fetch): Promise<RedirectMap> {
  if (cached && cached.until > now) return cached.map;
  if (pending) return pending;
  pending = (async () => {
    try {
      const response = await fetchImpl(`${apiBase.replace(/\/+$/, "")}/redirects`, {
        headers: { Accept: "application/json" },
        cache: "no-store",
        signal: AbortSignal.timeout(TIMEOUT_MS),
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      cached = { map: parseRedirects(await response.json()), until: now + TTL_MS };
    } catch (error) {
      console.error("redirects: keeping the last list", error instanceof Error ? error.message : error);
      cached = { map: cached?.map ?? new Map(), until: now + TTL_MS };
    } finally {
      pending = null;
    }
    return cached.map;
  })();
  return pending;
}

/** Tests only. */
export function resetRedirectCache(): void {
  cached = null;
  pending = null;
}
