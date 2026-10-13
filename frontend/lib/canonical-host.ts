import { siteUrl } from "./site.ts";

// The corporate site lives on one address (D-033: https://paxofi.com). Requests
// that reach it under another name it is known by (www.paxofi.com, the old
// corporate.paxofi.com) are sent there permanently, keeping the path and query.
// REDIRECT_HOSTS lists those names (comma-separated); by default only the www
// name, so deploying a release never redirects a host by surprise. The old
// corporate.paxofi.com is redirected by cPanel (guide Step 10m) or by listing it
// here. Staging and careers are separate hosts and are never redirected.

/** Host names that must redirect to the canonical site address. */
export function redirectHosts(env: Record<string, string | undefined> = process.env): string[] {
  const canonical = new URL(siteUrl(env)).host;
  const configured = env.REDIRECT_HOSTS?.trim();
  const hosts = configured !== undefined && configured !== ""
    ? configured.split(",")
    : [`www.${canonical}`];
  return hosts.map((host) => host.trim().toLowerCase()).filter((host) => host !== "" && host !== canonical);
}

/** The canonical URL for a request made under a redirecting host name, or null to serve it as it is. */
export function canonicalRedirect(hostHeader: string | null, pathname: string, search: string, env: Record<string, string | undefined> = process.env): string | null {
  const host = (hostHeader ?? "").trim().toLowerCase().replace(/:\d+$/, "");
  if (host === "" || !redirectHosts(env).includes(host)) return null;
  return `${siteUrl(env)}${pathname.startsWith("/") ? pathname : `/${pathname}`}${search}`;
}
