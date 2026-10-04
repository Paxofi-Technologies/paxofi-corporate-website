import { NextResponse, type NextRequest } from "next/server";
import { isCareersSite, sectionPath } from "@/lib/careers";
import { resolveApiBase } from "@/lib/contact";
import { contentSecurityPolicy, createNonce } from "@/lib/csp";
import { isStaging, stagingGate } from "@/lib/staging";

// Sets the Content-Security-Policy on every page response, per request:
// - a fresh script nonce, which Next.js applies to its own scripts when it
//   finds the policy on the request (pages render per request; layout.tsx);
// - connect-src from the runtime API_BASE_URL, which next.config.ts headers
//   cannot read after the build.
// The development server needs eval for hot reloading, so CSP is production-only.
export function proxy(request: NextRequest) {
  // Staging copy (D-013): password first, for every path, before anything else.
  const gate = stagingGate(request.headers.get("authorization"));
  if (gate) return new NextResponse(gate.body, { status: gate.status, headers: gate.headers });
  const response = route(request);
  // Staging is never indexed, whatever the page says.
  if (isStaging()) response.headers.set("X-Robots-Tag", "noindex, nofollow");
  return response;
}

function route(request: NextRequest): NextResponse {
  // careers.paxofi.com (D-018): the same build with SITE_SECTION=careers serves
  // the app/careers-site pages at its root; the corporate site never shows them.
  const section = sectionPath(request.nextUrl.pathname, isCareersSite());
  // A clone of nextUrl keeps the server's own address, so Next serves the
  // rewrite itself instead of proxying it (the Host header is the public name).
  let target: URL | null = null;
  if (section !== null) {
    target = request.nextUrl.clone();
    target.pathname = section === "not-found" ? "/_not-a-page" : section;
  }

  // /admin → the inbox as a bare redirect. A page-level redirect() sends a full
  // HTML page (ZAP 10044 "Big Redirect"); Next writes the target URL as the
  // body here, so label it (ZAP 10019 "Content-Type Header Missing").
  if (!target && request.nextUrl.pathname === "/admin") {
    const response = NextResponse.redirect(new URL("/admin/enquiries", request.url), 307);
    response.headers.set("Content-Type", "text/plain; charset=utf-8");
    return response;
  }
  if (process.env.NODE_ENV !== "production") return target ? NextResponse.rewrite(target) : NextResponse.next();

  const nonce = createNonce();
  const policy = contentSecurityPolicy(nonce, resolveApiBase(process.env));
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", policy);

  const response = target
    ? NextResponse.rewrite(target, { request: { headers: requestHeaders } })
    : NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set("Content-Security-Policy", policy);
  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image).*)"],
};
