/**
 * careers.paxofi.com (decisions D-018, D-019). The same release runs the
 * corporate website and, as a second cPanel Node app with SITE_SECTION=careers,
 * the careers site. proxy.ts sends careers-site paths to the internal
 * /careers-site routes and hides those routes on the corporate site.
 * Framework-free so it can be unit tested.
 */

/** Internal folder of the careers site's pages (app/careers-site). */
export const CAREERS_PREFIX = "/careers-site";

export const CV_MAX_BYTES = 5 * 1024 * 1024;
export const CV_ACCEPT = ".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
export const HOURS_OPTIONS = [15, 20, 25, 30, 35, 40] as const;
export const RECRUITMENT_EMAIL = "hr@paxofi.com";

/** "How did you hear about this role?" (P3.1); the API keeps the same keys (ApplicationInput::SOURCES). */
export const SOURCE_OPTIONS = [
  { value: "linkedin", label: "LinkedIn" },
  { value: "x", label: "X (Twitter)" },
  { value: "facebook", label: "Facebook" },
  { value: "instagram", label: "Instagram" },
  { value: "whatsapp", label: "WhatsApp" },
  { value: "referral", label: "A friend or colleague" },
  { value: "school", label: "University, school or bootcamp" },
  { value: "job_board", label: "A job board" },
  { value: "paxofi_website", label: "The Paxofi website" },
  { value: "search", label: "A search engine" },
  { value: "other", label: "Somewhere else" },
] as const;

export type Campaign = { utm_source?: string; utm_medium?: string; utm_campaign?: string };
export const CAMPAIGN_STORAGE_KEY = "paxofi.careers.campaign";
const CAMPAIGN_KEYS = ["utm_source", "utm_medium", "utm_campaign"] as const;

/** Campaign tags from a link such as ?utm_source=linkedin&utm_campaign=pif-2026; only safe characters are kept. */
export function campaignFrom(search: string): Campaign | null {
  const params = new URLSearchParams(search);
  const campaign: Campaign = {};
  for (const key of CAMPAIGN_KEYS) {
    const value = (params.get(key) ?? "").trim().toLowerCase();
    if (/^[a-z0-9._-]{1,80}$/.test(value)) campaign[key] = value;
  }
  return Object.keys(campaign).length > 0 ? campaign : null;
}

export type CareerRole = {
  slug: string;
  code: string;
  title: string;
  family: string;
  summary: string;
  purpose: string;
  responsibilities: string[];
  deliverables: string[];
  competencies: string[];
  tools: string[];
  evidence: string;
  assessment: string;
  interview: string;
};

export function isCareersSite(env: Record<string, string | undefined> = process.env): boolean {
  return env.SITE_SECTION?.trim().toLowerCase() === "careers";
}

/** The careers site's own address (canonical links, sitemap), read at run time. */
export function careersSiteUrl(env: Record<string, string | undefined> = process.env): string {
  return (env.CAREERS_SITE_URL?.trim() || "https://careers.paxofi.com").replace(/\/+$/, "");
}

/**
 * Where a request goes for the site section this app serves. Returns the
 * internal path to rewrite to, "not-found" for paths this section must not
 * serve, or null to leave the request alone.
 */
export function sectionPath(pathname: string, careers: boolean): string | "not-found" | null {
  const internal = pathname === CAREERS_PREFIX || pathname.startsWith(`${CAREERS_PREFIX}/`);
  if (!careers) return internal ? "not-found" : null;
  if (internal) return "not-found";
  // Files (og-image.png, robots.txt, sitemap.xml, icon.svg) and Next.js internals stay as they are.
  if (pathname.startsWith("/_next/") || /\/[^/]*\.[a-z0-9]+$/i.test(pathname)) return null;
  // The staff area is on the corporate site only.
  if (pathname === "/admin" || pathname.startsWith("/admin/")) return "not-found";
  return pathname === "/" ? CAREERS_PREFIX : CAREERS_PREFIX + pathname.replace(/\/+$/, "");
}

function apiRoot(apiBase: string | undefined): string {
  return (apiBase ?? "").trim().replace(/\/+$/, "") || "/api/v1";
}

export function cvUploadUrl(apiBase: string | undefined, filename: string): string {
  return `${apiRoot(apiBase)}/careers/cv?${new URLSearchParams({ filename })}`;
}

export function applyUrl(apiBase: string | undefined, slug: string): string {
  return `${apiRoot(apiBase)}/careers/roles/${encodeURIComponent(slug)}/apply`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

const strings = (value: unknown): string[] => (Array.isArray(value) ? value.filter((v): v is string => typeof v === "string") : []);

/** A role from the API, or null when the shape is wrong. */
export function parseRole(value: unknown): CareerRole | null {
  if (!isRecord(value) || typeof value.slug !== "string" || typeof value.title !== "string") return null;
  const text = (key: string) => (typeof value[key] === "string" ? (value[key] as string) : "");
  return {
    slug: value.slug,
    title: value.title,
    code: text("code"),
    family: text("family"),
    summary: text("summary"),
    purpose: text("purpose"),
    responsibilities: strings(value.responsibilities),
    deliverables: strings(value.deliverables),
    competencies: strings(value.competencies),
    tools: strings(value.tools),
    evidence: text("evidence"),
    assessment: text("assessment"),
    interview: text("interview"),
  };
}

const TIMEOUT_MS = 4000;

/** Open roles, or null when the API cannot be read (the page then says so and gives the HR email). */
export async function loadRoles(apiBase: string | undefined, fetchImpl: typeof fetch = fetch): Promise<CareerRole[] | null> {
  try {
    const response = await fetchImpl(`${apiRoot(apiBase)}/careers/roles`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (!response.ok) return null;
    const payload: unknown = await response.json();
    if (!isRecord(payload) || !Array.isArray(payload.data)) return null;
    return payload.data.map(parseRole).filter((role): role is CareerRole => role !== null);
  } catch {
    return null;
  }
}

/** One open role; "missing" when it is not open (404), null when the API cannot be read. */
export async function loadRole(apiBase: string | undefined, slug: string, fetchImpl: typeof fetch = fetch): Promise<CareerRole | "missing" | null> {
  if (!/^[a-z0-9-]{1,120}$/.test(slug)) return "missing";
  try {
    const response = await fetchImpl(`${apiRoot(apiBase)}/careers/roles/${slug}`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (response.status === 404) return "missing";
    if (!response.ok) return null;
    const payload: unknown = await response.json();
    return isRecord(payload) ? parseRole(payload.data) : null;
  } catch {
    return null;
  }
}

export type ApplicationFailure = { message: string; fields: Record<string, string> };

const GENERIC_FAILURE = `We could not send your application. Please try again, or email ${RECRUITMENT_EMAIL}.`;

/** Converts an API error into a message and per-field errors for the application form. */
export function describeApplicationFailure(status: number, body: unknown): ApplicationFailure {
  const error = isRecord(body) && isRecord(body.error) ? body.error : null;
  const fields: Record<string, string> = {};
  const details = error && isRecord(error.details) && isRecord(error.details.fields) ? error.details.fields : null;
  if (details) {
    for (const [key, value] of Object.entries(details)) {
      if (typeof value === "string" && value !== "") fields[key] = value;
    }
  }
  if (status === 429) return { message: "We have received several applications from your connection in a short time. Please wait an hour and try again.", fields };
  if (status === 404) return { message: "This role is no longer open. Please choose another role.", fields };
  if (status === 503) return { message: typeof error?.message === "string" ? error.message : GENERIC_FAILURE, fields };
  if (status === 422) return { message: typeof error?.message === "string" ? error.message : "Please correct the highlighted fields.", fields };
  return { message: GENERIC_FAILURE, fields };
}

/** Checks a chosen CV before upload (the API checks the content again). */
export function checkCvFile(name: string, size: number): string | null {
  if (!/\.(pdf|docx)$/i.test(name)) return "Upload your CV as a PDF or Word (.docx) file.";
  if (size > CV_MAX_BYTES) return "Your CV can be up to 5 MB.";
  if (size === 0) return "This file is empty. Choose your CV again.";
  return null;
}
