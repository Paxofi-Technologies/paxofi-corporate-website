/**
 * Content-Security-Policy for pages. Built per request (see proxy.ts) so the
 * contact form's API origin comes from the runtime API_BASE_URL setting
 * instead of allowing every HTTPS origin.
 *
 * Next.js inlines small bootstrap scripts (and React may emit inline styles),
 * so 'unsafe-inline' is required without a per-request nonce setup.
 */
export function contentSecurityPolicy(apiBase?: string): string {
  const connect = ["'self'", apiOrigin(apiBase)].filter(Boolean).join(" ");
  return [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data:",
    "font-src 'self'",
    `connect-src ${connect}`,
    "form-action 'self'",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "object-src 'none'",
  ].join("; ");
}

/** Origin of an absolute http(s) API base, or "" for a relative/missing one. */
export function apiOrigin(apiBase?: string): string {
  if (!apiBase) return "";
  try {
    const url = new URL(apiBase);
    return url.protocol === "https:" || url.protocol === "http:" ? url.origin : "";
  } catch {
    return "";
  }
}
