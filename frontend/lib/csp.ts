/**
 * Content-Security-Policy for pages, built per request in proxy.ts:
 * - scripts run only with this response's nonce ('strict-dynamic' lets those
 *   scripts load the site's own chunks), so injected inline script cannot run;
 * - connect-src names only the contact form's API origin, taken from the
 *   runtime API_BASE_URL setting.
 */
export function contentSecurityPolicy(nonce: string, apiBase?: string): string {
  const connect = ["'self'", apiOrigin(apiBase)].filter(Boolean).join(" ");
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'`,
    "style-src 'self'",
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

/** A fresh, unguessable nonce (128 bits, base64). */
export function createNonce(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return btoa(String.fromCharCode(...bytes));
}
