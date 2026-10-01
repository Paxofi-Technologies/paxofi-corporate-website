/**
 * Contact form API helpers. Kept framework-free so they can be unit tested
 * with the Node test runner.
 */

export const CONTACT_FORM_PATH = "/forms/contact/submit";

export type ContactFieldErrors = Partial<Record<"name" | "email" | "company" | "message", string>>;

export type SubmissionFailure = { message: string; fields: ContactFieldErrors };

const GENERIC_FAILURE = "We could not send your enquiry. Please try again, or email hello@paxofi.com.";

/**
 * Builds the contact submission URL from the configured API base
 * (NEXT_PUBLIC_API_URL, e.g. "https://api.paxofi.com/api/v1").
 * Defaults to the same-origin API path when no base is configured.
 */
export function contactEndpoint(apiBase?: string | null): string {
  const base = (apiBase ?? "").trim().replace(/\/+$/, "") || "/api/v1";
  return base.endsWith(CONTACT_FORM_PATH) ? base : `${base}${CONTACT_FORM_PATH}`;
}

/** Converts an API error response into a user-facing message and per-field errors. */
export function describeFailure(status: number, body: unknown): SubmissionFailure {
  const error = isRecord(body) && isRecord(body.error) ? body.error : null;
  const fields: ContactFieldErrors = {};

  const details = error && isRecord(error.details) && isRecord(error.details.fields) ? error.details.fields : null;
  if (details) {
    for (const key of ["name", "email", "company", "message"] as const) {
      const value = details[key];
      if (typeof value === "string" && value !== "") fields[key] = value;
    }
  }

  if (status === 429) {
    return { message: "You've sent several enquiries in a short time. Please wait a few minutes and try again.", fields };
  }
  if (status === 422) {
    const message = typeof error?.message === "string" ? error.message : "Please correct the highlighted fields.";
    return { message, fields };
  }

  return { message: GENERIC_FAILURE, fields };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
