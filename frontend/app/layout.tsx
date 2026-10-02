import "./globals.css";
import type { Metadata, Viewport } from "next";
import SiteFooter from "@/components/SiteFooter";
import SiteHeader from "@/components/SiteHeader";
import { SITE, organizationJsonLd, siteUrl } from "@/lib/site";

// Render every page per request. Prerendered pages are sent with
// "Cache-Control: s-maxage=31536000", which lets the host's shared cache
// (LiteSpeed on cPanel) keep serving an old release for up to a year after a
// deployment, and the cache cannot be purged from this account. Dynamic pages
// are sent as "private, no-cache, no-store". The site is small, so rendering per
// request is cheap; hashed /_next/static assets are still cached long-term.
export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl()),
  title: { default: `${SITE.name} — Building Digital Infrastructure`, template: `%s | ${SITE.name}` },
  description: SITE.description,
  openGraph: { siteName: SITE.name, type: "website", url: "/", title: SITE.name, description: SITE.description },
  twitter: { card: "summary", title: SITE.name, description: SITE.description },
  robots: { index: true, follow: true },
};

export const viewport: Viewport = { themeColor: "#111111", width: "device-width", initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <a className="skip-link" href="#main">Skip to content</a>
        <SiteHeader />
        <main id="main" tabIndex={-1}>{children}</main>
        <SiteFooter />
        <script
          type="application/ld+json"
          // Static, build-time data only; never interpolate user input here.
          dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd()) }}
        />
      </body>
    </html>
  );
}
