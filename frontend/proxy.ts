import { NextResponse, type NextRequest } from "next/server";
import { resolveApiBase } from "@/lib/contact";
import { contentSecurityPolicy, createNonce } from "@/lib/csp";

// Sets the Content-Security-Policy on every page response, per request:
// - a fresh script nonce, which Next.js applies to its own scripts when it
//   finds the policy on the request (pages render per request; layout.tsx);
// - connect-src from the runtime API_BASE_URL, which next.config.ts headers
//   cannot read after the build.
// The development server needs eval for hot reloading, so CSP is production-only.
export function proxy(request: NextRequest) {
  // /admin → the inbox, as a bare redirect: a page-level redirect() would send
  // a full HTML body with the 307 (ZAP 10044 "Big Redirect").
  if (request.nextUrl.pathname === "/admin") {
    return NextResponse.redirect(new URL("/admin/enquiries", request.url), 307);
  }
  if (process.env.NODE_ENV !== "production") return NextResponse.next();

  const nonce = createNonce();
  const policy = contentSecurityPolicy(nonce, resolveApiBase(process.env));
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set("Content-Security-Policy", policy);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set("Content-Security-Policy", policy);
  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image).*)"],
};
