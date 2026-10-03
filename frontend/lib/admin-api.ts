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
  role: "administrator" | "business_development" | null;
  role_label: string | null;
  permissions: string[];
  last_login_at: string | null;
  two_factor_enabled?: boolean;
  /** Administrators must set up two-factor sign-in before using the staff area (D-010). */
  two_factor_enrollment_required?: boolean;
};

/** Products and services edited in the staff area (D-011). */
export type CatalogKind = "products" | "services";
export type CatalogContent = { name: string; label: string | null; icon: string; summary: string; points: string[]; sort_order: number };
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
