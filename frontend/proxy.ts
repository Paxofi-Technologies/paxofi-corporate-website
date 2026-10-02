import { NextResponse } from "next/server";
import { resolveApiBase } from "@/lib/contact";
import { contentSecurityPolicy } from "@/lib/csp";

// Adds the Content-Security-Policy to every page response. It is set here, at
// request time, because connect-src must name the API origin configured on the
// server (API_BASE_URL), which next.config.ts headers cannot read after build.
// The development server needs eval for hot reloading, so CSP is production-only.
export function proxy() {
  const response = NextResponse.next();
  if (process.env.NODE_ENV === "production") {
    response.headers.set("Content-Security-Policy", contentSecurityPolicy(resolveApiBase(process.env)));
  }
  return response;
}

export const config = {
  matcher: ["/((?!_next/static|_next/image).*)"],
};
