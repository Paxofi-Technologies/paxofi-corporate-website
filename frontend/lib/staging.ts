/**
 * Staging mode (decision D-013). The same release package runs the live site
 * and the staging copy; the Node app's settings decide which one it is:
 *
 *   SITE_ENVIRONMENT=staging   turns staging mode on
 *   STAGING_PASSWORD=…         password every visitor must enter (12+ characters)
 *
 * In staging mode every page needs the password (HTTP Basic), search engines
 * are told not to index anything, and a banner says it is a test copy. Without
 * a usable password the staging site refuses to serve pages at all, so it can
 * never be public by mistake. Framework-free for unit tests.
 */

type Env = Record<string, string | undefined>;

export const STAGING_REALM = "Paxofi staging";
export const MIN_PASSWORD_LENGTH = 12;

export function isStaging(env: Env = process.env): boolean {
  return (env.SITE_ENVIRONMENT ?? "").trim().toLowerCase() === "staging";
}

/** The configured password, or null when it is missing or too short. */
export function stagingPassword(env: Env = process.env): string | null {
  const password = (env.STAGING_PASSWORD ?? "").trim();
  return password.length >= MIN_PASSWORD_LENGTH ? password : null;
}

/** True when an Authorization header carries the password (any user name). */
export function hasStagingAccess(authorization: string | null | undefined, password: string): boolean {
  const match = /^Basic\s+([A-Za-z0-9+/=]+)\s*$/i.exec(authorization ?? "");
  if (!match) return false;
  let decoded: string;
  try {
    decoded = new TextDecoder().decode(Uint8Array.from(atob(match[1]), (c) => c.charCodeAt(0)));
  } catch {
    return false;
  }
  const colon = decoded.indexOf(":");
  return colon >= 0 && constantTimeEqual(decoded.slice(colon + 1), password);
}

export type StagingGate = { status: 401 | 503; headers: Record<string, string>; body: string } | null;

/**
 * What the staging site answers before a page is rendered: null to continue,
 * or a refusal (401 asks for the password; 503 when no password is set).
 */
export function stagingGate(authorization: string | null | undefined, env: Env = process.env): StagingGate {
  if (!isStaging(env)) return null;
  const text = { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store", "X-Robots-Tag": "noindex, nofollow" };
  const password = stagingPassword(env);
  if (!password) {
    return { status: 503, headers: text, body: "Staging site: STAGING_PASSWORD is not set (12 or more characters).\n" };
  }
  if (hasStagingAccess(authorization, password)) return null;
  return {
    status: 401,
    headers: { ...text, "WWW-Authenticate": `Basic realm="${STAGING_REALM}", charset="UTF-8"` },
    body: "Staging site: sign in with the staging password.\n",
  };
}

function constantTimeEqual(a: string, b: string): boolean {
  const x = new TextEncoder().encode(a);
  const y = new TextEncoder().encode(b);
  let difference = x.length ^ y.length;
  for (let i = 0; i < Math.max(x.length, y.length); i++) difference |= (x[i] ?? 0) ^ (y[i] ?? 0);
  return difference === 0;
}
