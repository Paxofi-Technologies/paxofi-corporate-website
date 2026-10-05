/**
 * Staff admin API client (decision D-009). Framework-free so it can be unit
 * tested. Requests carry the session cookie (credentials: "include"); the API
 * sets it HttpOnly, so scripts never see the token.
 */

export type StaffUser = {
  id: string;
  email: string;
  display_name: string;
  status: "active" | "disabled";
  role: "administrator" | "business_development" | "human_resources" | null;
  role_label: string | null;
  permissions: string[];
  last_login_at: string | null;
  two_factor_enabled?: boolean;
  /** Administrators and HR staff must set up two-factor sign-in before using the staff area (D-010, D-019). */
  two_factor_enrollment_required?: boolean;
};

/** Products and services edited in the staff area (D-011). */
export type CatalogKind = "products" | "services";
export type CatalogContent = {
  name: string;
  label: string | null;
  icon: string;
  summary: string;
  points: string[];
  sort_order: number;
  image_id?: string | null;
  document_id?: string | null;
};
export type CatalogSummary = { id: string; slug: string; name: string; visible: boolean; has_draft: boolean; sort_order: number; updated_at: string | null };
export type CatalogDetail = {
  item: CatalogSummary & { content: CatalogContent };
  draft: { content: CatalogContent; saved_at: string; author_name: string | null } | null;
  revisions: { id: string; state: string; created_at: string; author_name: string | null; content: CatalogContent }[];
};
export const CATALOG_KINDS: { value: CatalogKind; label: string; singular: string }[] = [
  { value: "products", label: "Products", singular: "product" },
  { value: "services", label: "Services", singular: "service" },
];

/** Page text edited in the staff area (D-015). Fields come from page-copy.json via the API. */
export type PageTextSummary = { page: string; label: string; path: string; published_at: string | null; has_draft: boolean };
export type PageTextField = { key: string; group: string; label: string; kind: "line" | "text" | "link" | "email"; max: number; default: string; optional?: boolean; pair?: string };
export type PageTextDetail = {
  page: string;
  label: string;
  path: string;
  fields: PageTextField[];
  live: { fields: Record<string, string>; published_at: string } | null;
  draft: { fields: Record<string, string>; saved_at: string; author_name: string | null } | null;
  revisions: { id: string; created_at: string; author_name: string | null }[];
};

/** A file in the media library (D-012). path is on the API host: /api/v1/media/{id}/{filename}. */
export type MediaItem = {
  id: string;
  kind: "image" | "document";
  filename: string;
  media_type: string;
  format: string;
  size_bytes: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  title: string | null;
  path: string;
  uploaded_by: string | null;
  created_at: string | null;
  used_by: string[];
};
export type MediaCapabilities = { uploads: boolean; images: boolean; image_max_bytes: number; document_max_bytes: number; server_max_bytes: number | null };

/** File types the media library accepts (the API checks the content, not just the name). */
export const MEDIA_ACCEPT = ".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx,.pptx,.txt,.csv,image/jpeg,image/png,image/webp,application/pdf";
const IMAGE_EXTENSIONS = /\.(jpe?g|png|webp)$/i;

/** True when a chosen file will be treated as an image (it then needs a description). */
export function isImageFile(name: string, type = ""): boolean {
  return /^image\/(jpeg|png|webp)$/.test(type) || IMAGE_EXTENSIONS.test(name);
}

/** Absolute URL of a media file on the API host. */
export function mediaHref(apiBase: string | undefined, path: string): string {
  const base = (apiBase ?? "").trim();
  try {
    return new URL(path, /^https?:\/\//.test(base) ? base : window.location.origin).href;
  } catch {
    return path;
  }
}

export function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1).replace(/\.0$/, "")} MB`;
  return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Uploads one file as the request body (D-012), with its description or
 * title in the query. Errors come back as AdminApiError like other calls.
 */
export async function uploadMedia(
  apiBase: string | undefined,
  file: Blob & { name: string },
  details: { alt_text?: string; title?: string },
  fetchImpl: typeof fetch = fetch,
): Promise<MediaItem> {
  const query = new URLSearchParams({ filename: file.name });
  if (details.alt_text) query.set("alt_text", details.alt_text);
  if (details.title) query.set("title", details.title);
  let response: Response;
  try {
    response = await fetchImpl(`${adminUrl(apiBase, "/media")}?${query}`, {
      method: "POST",
      credentials: "include",
      headers: { Accept: "application/json", "Content-Type": "application/octet-stream" },
      body: file,
      cache: "no-store",
    });
  } catch {
    throw new AdminApiError(0, "NETWORK", "The file could not be sent. Check your connection and try again; very large files may be refused by the server.");
  }
  const payload: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 413) {
      throw new AdminApiError(413, "TOO_LARGE", "The server refused a file this large. Images can be up to 5 MB and documents up to 10 MB.", { file: "Too large." });
    }
    throw toError(response.status, payload);
  }
  return (isRecord(payload) ? payload.data : null) as MediaItem;
}

/** Visitor analytics (D-014). */
export type AnalyticsReport = {
  days: number;
  from: string;
  to: string;
  totals: { views: number; visitors: number; enquiries: number };
  daily: { day: string; views: number; visitors: number; enquiries: number }[];
  pages: { path: string; views: number; visitors: number }[];
  sources: { source: string; views: number }[];
  devices: { device: string; views: number }[];
};

/** Recruitment (D-019): applications from careers.paxofi.com. */
export type StageOption = { value: string; label: string; closed: boolean };
export type ApplicationRow = {
  id: string;
  reference: string;
  full_name: string;
  email: string;
  role_title: string;
  stage: string;
  stage_label: string;
  created_at: string;
  evidence_total: number | null;
  interview_total: number | null;
  has_cv: boolean;
};
export type ScoreSummary = { scores: Record<string, number>; total: number; out_of: number; pass: number; passed: boolean } | null;
export type ScorecardDefinition = { label: string; pass: number; criteria: Record<string, string> };
export type ApplicationNote = { id: string; kind: "note" | "stage" | "score" | "email"; body: string; author_name: string | null; created_at: string };
export type EmailTemplate = { key: string; label: string; subject: string; body: string; stage: string | null };
export type ApplicationDetail = {
  id: string;
  reference: string;
  role: { id: string; title: string; slug: string };
  full_name: string;
  email: string;
  phone: string | null;
  location: string;
  hours_per_week: number;
  portfolio_url: string | null;
  linkedin_url: string | null;
  motivation: string;
  experience: string;
  source: { value: string; label: string } | null;
  campaign: { source: string | null; medium: string | null; campaign: string | null } | null;
  cv: { filename: string; size_bytes: number; media_type: string } | null;
  stage: string;
  stage_label: string;
  stage_changed_at: string;
  closed_at: string | null;
  created_at: string;
  evidence: ScoreSummary;
  interview: ScoreSummary;
  notes: ApplicationNote[];
  stages: StageOption[];
  scorecards: Record<"evidence" | "interview", ScorecardDefinition>;
  email_templates: EmailTemplate[];
  email_available: boolean;
};

/** The recruitment report (P3.1): counts only. */
export type RecruitmentReportData = {
  days: number | null;
  total: number;
  by_role: { key: string | null; label: string; count: number }[];
  by_stage: { key: string | null; label: string; count: number }[];
  by_source: { key: string | null; label: string; count: number }[];
  by_campaign: { key: string | null; label: string; count: number }[];
  by_day: { day: string; count: number }[];
  review: { target_working_days: number; reviewed: number; on_time: number; waiting: number; overdue: number; median_hours: number | null };
};

/** Roles on careers.paxofi.com (D-018). */
export type CareerRoleState = "draft" | "published" | "closed";
export type CareerRoleAdmin = {
  id: string;
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
  state: CareerRoleState;
  sort_order: number;
  published_at: string | null;
  updated_at: string | null;
};

/**
 * Downloads a CV through the API with the staff session (D-019) and saves it,
 * so the file never sits at a shareable address.
 */
export async function downloadCv(apiBase: string | undefined, id: string, fetchImpl: typeof fetch = fetch): Promise<void> {
  let response: Response;
  try {
    response = await fetchImpl(adminUrl(apiBase, `/applications/${encodeURIComponent(id)}/cv`), { credentials: "include", cache: "no-store" });
  } catch {
    throw new AdminApiError(0, "NETWORK", NETWORK_FAILURE);
  }
  if (!response.ok) throw toError(response.status, await response.json().catch(() => null));
  const name = /filename="([^"]+)"/.exec(response.headers.get("content-disposition") ?? "")?.[1] ?? "CV";
  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement("a");
  link.href = url;
  link.download = name;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

export type TwoFactorStatus = { configured: boolean; enabled: boolean; required: boolean; recovery_codes_left: number };

/** Path of the page where staff set up two-factor sign-in. */
export const TWO_FACTOR_PATH = "/admin/account/two-factor";

export type EnquiryStatus = "new" | "in_progress" | "replied" | "closed" | "spam";

export const ENQUIRY_STATUSES: { value: EnquiryStatus; label: string }[] = [
  { value: "new", label: "New" },
  { value: "in_progress", label: "In progress" },
  { value: "replied", label: "Replied" },
  { value: "closed", label: "Closed" },
  { value: "spam", label: "Spam" },
];

export function statusLabel(status: string): string {
  return ENQUIRY_STATUSES.find((s) => s.value === status)?.label ?? status;
}

export class AdminApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fields: Record<string, string> = {},
  ) {
    super(message);
  }
}

const NETWORK_FAILURE = "The admin service could not be reached. Check your connection and try again.";

/** API base ending in /api/v1, from the server's API_BASE_URL (same-origin fallback). */
export function adminUrl(apiBase: string | undefined, path: string): string {
  const base = (apiBase ?? "").trim().replace(/\/+$/, "") || "/api/v1";
  return `${base}/admin${path}`;
}

export type Envelope<T> = { data: T; meta: Record<string, unknown> };

export async function adminRequest<T>(
  apiBase: string | undefined,
  path: string,
  init: { method?: string; body?: unknown } = {},
  fetchImpl: typeof fetch = fetch,
): Promise<Envelope<T>> {
  let response: Response;
  try {
    response = await fetchImpl(adminUrl(apiBase, path), {
      method: init.method ?? "GET",
      credentials: "include",
      headers: init.body === undefined ? { Accept: "application/json" } : { Accept: "application/json", "Content-Type": "application/json" },
      body: init.body === undefined ? undefined : JSON.stringify(init.body),
      cache: "no-store",
    });
  } catch {
    throw new AdminApiError(0, "NETWORK", NETWORK_FAILURE);
  }

  const payload: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    throw toError(response.status, payload);
  }
  const body = isRecord(payload) ? payload : {};
  return { data: body.data as T, meta: isRecord(body.meta) ? body.meta : {} };
}

export function toError(status: number, payload: unknown): AdminApiError {
  const error = isRecord(payload) && isRecord(payload.error) ? payload.error : {};
  const code = typeof error.code === "string" ? error.code : "HTTP_" + status;
  const fields: Record<string, string> = {};
  const details = isRecord(error.details) && isRecord(error.details.fields) ? error.details.fields : {};
  for (const [key, value] of Object.entries(details)) {
    if (typeof value === "string") fields[key] = value;
  }
  const message =
    status === 429
      ? "Too many attempts. Please wait 15 minutes and try again."
      : status >= 500
        ? "Something went wrong on our side. Please try again in a moment."
        : typeof error.message === "string" && error.message.length <= 200
          ? error.message
          : "The request could not be completed.";
  return new AdminApiError(status, code, message, fields);
}

/** Only same-site admin paths may be used as a post-sign-in destination. */
export function safeNextPath(next: string | null | undefined): string {
  return next && /^\/admin(\/[A-Za-z0-9/_-]*)?$/.test(next) && !next.startsWith("//") ? next : "/admin/enquiries";
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value.includes("T") ? value : value.replace(" ", "T") + "Z");
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short" }).format(date);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
