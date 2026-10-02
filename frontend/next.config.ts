import type { NextConfig } from "next";

// Next.js inlines small bootstrap scripts (and React may emit inline styles),
// so 'unsafe-inline' is required without a per-request nonce setup. The contact
// form posts to the API origin, which can change at runtime (API_BASE_URL), so
// connect-src allows any HTTPS origin.
const contentSecurityPolicy = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data:",
  "font-src 'self'",
  "connect-src 'self' https:",
  "form-action 'self'",
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "object-src 'none'",
].join("; ");

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
];

// Production only: the development server needs eval for hot reloading and runs
// over plain HTTP.
const productionHeaders = [
  { key: "Content-Security-Policy", value: contentSecurityPolicy },
  { key: "Strict-Transport-Security", value: "max-age=31536000" },
];

const nextConfig: NextConfig = {
  // Self-contained server bundle (server.js + traced node_modules) for the
  // cPanel upload package; see ops/package-release.sh.
  output: process.env.NEXT_OUTPUT_STANDALONE === "1" ? "standalone" : undefined,
  reactStrictMode: true,
  poweredByHeader: false,
  async headers() {
    const headers = process.env.NODE_ENV === "production" ? [...securityHeaders, ...productionHeaders] : securityHeaders;
    return [
      { source: "/(.*)", headers },
      // Always fresh, so it reliably shows which release is live.
      { source: "/release.txt", headers: [{ key: "Cache-Control", value: "no-store" }] },
    ];
  },
};

export default nextConfig;
