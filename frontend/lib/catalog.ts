/**
 * Products and services shown on the public site (decision D-011).
 *
 * Content comes from the API (edited in the staff area). If the API is slow,
 * down or returns something unexpected, the built-in V1 copy below is shown
 * instead, so the pages never break. Framework-free for unit tests.
 */

export type CatalogType = "products" | "services";

export type CatalogItem = {
  slug: string;
  name: string;
  label: string | null;
  icon: string;
  summary: string;
  points: string[];
};

/** Icons the staff area can choose; backend CatalogContent::ICONS keeps the same list. */
export const CATALOG_ICONS = [
  "shield-check",
  "cog",
  "code",
  "network",
  "rocket",
  "workflow",
  "cloud",
  "megaphone",
  "layers",
  "globe",
  "lightbulb",
  "briefcase",
  "smartphone",
  "database",
  "lock",
] as const;

/** The V1 copy, used whenever the API cannot be read. */
export const FALLBACK_CATALOG: Record<CatalogType, CatalogItem[]> = {
  products: [
    {
      slug: "paxofi-pay",
      name: "Paxofi Pay",
      label: "Paxofi Product",
      icon: "shield-check",
      summary:
        "Digital payments infrastructure designed around reliability, transaction certainty, transparency, recovery and trust.",
      points: [
        "Transaction certainty",
        "Transparency and traceability",
        "Recovery built in",
      ],
    },
    {
      slug: "paxofi-core-framework",
      name: "Paxofi Core Framework",
      label: "Paxofi Technology",
      icon: "cog",
      summary:
        "An independent PHP application framework for maintainable internal, client, SaaS and API systems.",
      points: [
        "Layered, testable architecture",
        "Secure HTTP and data foundations",
        "Built for long-lived systems",
      ],
    },
  ],
  services: [
    {
      slug: "software-web-engineering",
      name: "Software & Web Engineering",
      label: null,
      icon: "code",
      summary:
        "Web platforms and business applications engineered for reliability, security and maintainability.",
      points: [],
    },
    {
      slug: "api-platform-development",
      name: "API & Platform Development",
      label: null,
      icon: "network",
      summary:
        "Well-documented APIs and platform services that connect products, partners and data.",
      points: [],
    },
    {
      slug: "product-strategy-prototyping",
      name: "Product Strategy & Prototyping",
      label: null,
      icon: "rocket",
      summary:
        "Clarify the problem, shape the product and validate it with working prototypes.",
      points: [],
    },
    {
      slug: "digital-transformation",
      name: "Digital Transformation",
      label: null,
      icon: "workflow",
      summary:
        "Modernise processes and systems with practical, measurable steps.",
      points: [],
    },
    {
      slug: "cloud-infrastructure-foundations",
      name: "Cloud & Infrastructure Foundations",
      label: null,
      icon: "cloud",
      summary:
        "Deployment, hosting, monitoring and recovery foundations that keep services running.",
      points: [],
    },
    {
      slug: "digital-marketing-growth",
      name: "Digital Marketing & Growth",
      label: null,
      icon: "megaphone",
      summary:
        "Web presence, content and digital channels that help the right people find you.",
      points: [],
    },
  ],
};

const TIMEOUT_MS = 1500;

/** Validates an API response; returns null when it is not a usable list. */
export function parseCatalog(payload: unknown): CatalogItem[] | null {
  const data =
    isRecord(payload) && Array.isArray(payload.data) ? payload.data : null;
  if (!data) return null;
  const items: CatalogItem[] = [];
  for (const raw of data) {
    if (
      !isRecord(raw) ||
      typeof raw.name !== "string" ||
      typeof raw.summary !== "string" ||
      raw.name.trim() === ""
    )
      continue;
    items.push({
      slug: typeof raw.slug === "string" ? raw.slug : raw.name,
      name: raw.name,
      label:
        typeof raw.label === "string" && raw.label !== "" ? raw.label : null,
      icon:
        typeof raw.icon === "string" &&
        (CATALOG_ICONS as readonly string[]).includes(raw.icon)
          ? raw.icon
          : "layers",
      summary: raw.summary,
      points: Array.isArray(raw.points)
        ? raw.points
            .filter((p): p is string => typeof p === "string" && p !== "")
            .slice(0, 5)
        : [],
    });
  }
  return items;
}

/** Published products or services from the API, or the built-in copy if the API cannot be read. */
export async function loadCatalog(type: CatalogType, apiBase: string | undefined, fetchImpl: typeof fetch = fetch): Promise<CatalogItem[]> {
  const base = (apiBase ?? "").trim().replace(/\/+$/, "");
  if (!/^https?:\/\//.test(base)) return fallback(type, "API_BASE_URL is not an absolute URL");
  try {
    const response = await fetchImpl(`${base}/${type}?per_page=50`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
      signal: AbortSignal.timeout(TIMEOUT_MS),
    });
    if (!response.ok) return fallback(type, `HTTP ${response.status}`);
    return parseCatalog(await response.json()) ?? fallback(type, "unexpected response");
  } catch (error) {
    return fallback(type, error instanceof Error ? error.message : "request failed");
  }
}

/** Built-in copy, with one line in the server log (cPanel: stderr.log) saying why. */
function fallback(type: CatalogType, reason: string): CatalogItem[] {
  if (process.env.NODE_ENV === "production") console.warn(`catalog: showing built-in ${type} (${reason})`);
  return FALLBACK_CATALOG[type];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
