/**
 * Products, services and industries shown on the public site (decisions D-011, D-022).
 *
 * Content comes from the API (edited in the staff area). If the API is slow,
 * down or returns something unexpected, the built-in V1 copy below is shown
 * instead, so the pages never break. Framework-free for unit tests.
 */

import { FALLBACK_INDUSTRIES } from "./industries-data";

export type CatalogType = "products" | "services" | "industries";

/** Product maturity (SRS 14.7, D-022); backend CatalogContent::STATUSES keeps the same list. */
export const PRODUCT_STATUSES = {
  planned: "Planned",
  in_development: "In development",
  pilot: "Pilot",
  beta: "Beta",
  available: "Available",
  limited: "Limited availability",
  paused: "Paused",
  retired: "Retired",
} as const;
export type ProductStatus = keyof typeof PRODUCT_STATUSES;

/** A published product or service an industry links to (D-022). */
export type RelatedItem = {
  type: "product" | "service";
  slug: string;
  name: string;
  summary: string;
  icon: string;
  status: ProductStatus | null;
};

/** A picture chosen in the staff area (D-012); src is an absolute URL on the API host. */
export type CatalogImage = { src: string; alt: string; width: number; height: number };
/** A downloadable document, e.g. a brochure (D-012). */
export type CatalogDocument = { href: string; title: string; format: string; sizeBytes: number };

export type CatalogItem = {
  slug: string;
  name: string;
  label: string | null;
  icon: string;
  summary: string;
  points: string[];
  image?: CatalogImage | null;
  document?: CatalogDocument | null;
  /** Products only. */
  status?: ProductStatus | null;
  /** Industries only: paragraphs separated by a blank line. */
  description?: string | null;
  /** Industries only. */
  related?: RelatedItem[];
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
  "banknote",
  "graduation-cap",
  "heart-pulse",
  "shopping-cart",
  "truck",
  "landmark",
  "sprout",
  "factory",
  "hand-heart",
  "store",
] as const;

export function isProductStatus(value: unknown): value is ProductStatus {
  return typeof value === "string" && Object.prototype.hasOwnProperty.call(PRODUCT_STATUSES, value);
}

/** Where a related item is shown: its card on /products or /services. */
export function relatedHref(item: Pick<RelatedItem, "type" | "slug">): string {
  return `/${item.type === "product" ? "products" : "services"}#${item.slug}`;
}

/** An industry description as paragraphs (blank line between paragraphs). */
export function paragraphs(text: string | null | undefined): string[] {
  return (text ?? "").split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
}

/** The V1 copy, used whenever the API cannot be read. */
const FALLBACK_PRODUCTS_AND_SERVICES: Record<"products" | "services", CatalogItem[]> = {
  products: [
    {
      slug: "paxofi-pay",
      name: "Paxofi Pay",
      label: "Paxofi Product",
      status: "planned",
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
      slug: "paxoficloud",
      name: "PaxofiCloud",
      label: "Paxofi Product",
      status: "in_development",
      icon: "cloud",
      summary:
        "A cloud and digital infrastructure platform for domains, web hosting, cloud servers, SSL and business email, bought, managed and supported through one Paxofi account.",
      points: ["Domains and DNS management", "Web hosting and cloud servers", "One account, one dashboard"],
    },
    {
      slug: "paxofi-core-framework",
      name: "Paxofi Core Framework",
      label: "Paxofi Technology",
      status: "available",
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

/** The built-in industries, linking to the built-in products and services. */
function fallbackIndustries(): CatalogItem[] {
  const published = new Map<string, RelatedItem>();
  for (const [type, items] of [["product", FALLBACK_PRODUCTS_AND_SERVICES.products], ["service", FALLBACK_PRODUCTS_AND_SERVICES.services]] as const) {
    for (const item of items) {
      published.set(`${type}:${item.slug}`, { type, slug: item.slug, name: item.name, summary: item.summary, icon: item.icon, status: item.status ?? null });
    }
  }
  return FALLBACK_INDUSTRIES.map((industry) => ({
    slug: industry.slug,
    name: industry.name,
    label: null,
    icon: industry.icon,
    summary: industry.summary,
    description: industry.description,
    points: [...industry.points],
    related: industry.related.map((reference) => published.get(reference)).filter((r): r is RelatedItem => r !== undefined),
  }));
}

export const FALLBACK_CATALOG: Record<CatalogType, CatalogItem[]> = {
  ...FALLBACK_PRODUCTS_AND_SERVICES,
  industries: fallbackIndustries(),
};

const TIMEOUT_MS = 1500;

/**
 * Absolute URL of a media file from its API path (/api/v1/media/…), on the
 * API's origin; null for anything else, so only our own files are linked.
 */
export function mediaUrl(apiBase: string | undefined, path: unknown): string | null {
  if (typeof path !== "string" || !/^\/api\/v1\/media\/[0-9a-f-]{36}\/[A-Za-z0-9._%-]+$/.test(path)) return null;
  try {
    const origin = new URL(apiBase ?? "").origin;
    return /^https?:\/\//.test(origin) ? origin + path : null;
  } catch {
    return null;
  }
}

/** "PDF, 1.2 MB": format and size for a download link. */
export function describeDownload(format: string, sizeBytes: number): string {
  const size =
    sizeBytes >= 1024 * 1024
      ? `${(sizeBytes / 1024 / 1024).toFixed(1).replace(/\.0$/, "")} MB`
      : `${Math.max(1, Math.round(sizeBytes / 1024))} KB`;
  return `${format}, ${size}`;
}

function parseImage(raw: unknown, apiBase: string | undefined): CatalogImage | null {
  if (!isRecord(raw)) return null;
  const src = mediaUrl(apiBase, raw.path);
  const width = Number(raw.width);
  const height = Number(raw.height);
  if (!src || typeof raw.alt !== "string" || !(width > 0) || !(height > 0)) return null;
  return { src, alt: raw.alt, width, height };
}

function parseDocument(raw: unknown, apiBase: string | undefined): CatalogDocument | null {
  if (!isRecord(raw)) return null;
  const href = mediaUrl(apiBase, raw.path);
  if (!href || typeof raw.title !== "string" || raw.title === "" || typeof raw.format !== "string") return null;
  return { href, title: raw.title, format: raw.format, sizeBytes: Number(raw.size_bytes) || 0 };
}

function parseRelated(raw: unknown): RelatedItem | null {
  if (!isRecord(raw) || (raw.type !== "product" && raw.type !== "service")) return null;
  if (typeof raw.slug !== "string" || !/^[a-z0-9-]{1,180}$/.test(raw.slug) || typeof raw.name !== "string" || raw.name === "") return null;
  return {
    type: raw.type,
    slug: raw.slug,
    name: raw.name,
    summary: typeof raw.summary === "string" ? raw.summary : "",
    icon: typeof raw.icon === "string" && (CATALOG_ICONS as readonly string[]).includes(raw.icon) ? raw.icon : "layers",
    status: isProductStatus(raw.status) ? raw.status : null,
  };
}

/** Validates an API response; returns null when it is not a usable list. */
export function parseCatalog(payload: unknown, apiBase?: string): CatalogItem[] | null {
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
      image: parseImage(raw.image, apiBase),
      document: parseDocument(raw.document, apiBase),
      status: isProductStatus(raw.status) ? raw.status : null,
      description: typeof raw.description === "string" && raw.description !== "" ? raw.description : null,
      related: Array.isArray(raw.related) ? raw.related.map(parseRelated).filter((r): r is RelatedItem => r !== null).slice(0, 6) : [],
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
    return parseCatalog(await response.json(), base) ?? fallback(type, "unexpected response");
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
