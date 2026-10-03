import "@fontsource-variable/inter";
import "./globals.css";
import type { Metadata, Viewport } from "next";
import { SHARE_IMAGE, SITE, siteUrl } from "@/lib/site";

// Render every page per request. Prerendered pages are sent with
// "Cache-Control: s-maxage=31536000", which lets the host's shared cache
// (LiteSpeed on cPanel) keep serving an old release for up to a year after a
// deployment, and the cache cannot be purged from this account. Dynamic pages
// are sent as "private, no-cache, no-store". The site is small, so rendering per
// request is cheap; hashed /_next/static assets are still cached long-term.
export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl()),
  title: { default: `${SITE.name} — Technology for a Brighter Tomorrow`, template: `%s | ${SITE.name}` },
  description: SITE.description,
  openGraph: { siteName: SITE.name, type: "website", url: "/", title: SITE.name, description: SITE.description, images: [SHARE_IMAGE] },
  twitter: { card: "summary_large_image", title: SITE.name, description: SITE.description, images: [SHARE_IMAGE.url] },
  robots: { index: true, follow: true },
};

export const viewport: Viewport = { themeColor: "#0A1F44", width: "device-width", initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  // Page chrome lives in the route groups: (site) for the public website,
  // admin/ for the staff area (decision D-009).
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
