import type { NextConfig } from "next";

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
  // Process isolation: no other site can hold a window reference to ours or
  // embed our resources, and pages load only same-origin or CORS resources.
  { key: "Cross-Origin-Opener-Policy", value: "same-origin" },
  { key: "Cross-Origin-Resource-Policy", value: "same-origin" },
  { key: "Cross-Origin-Embedder-Policy", value: "require-corp" },
];

// Production only: the development server runs over plain HTTP. The
// Content-Security-Policy is set per request in proxy.ts.
const productionHeaders = [{ key: "Strict-Transport-Security", value: "max-age=31536000" }];

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
