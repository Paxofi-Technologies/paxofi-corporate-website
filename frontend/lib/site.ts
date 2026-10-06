import type { Metadata } from "next";

/** Public site settings shared by metadata, sitemap, robots and navigation. */
export const SITE = {
  name: "Paxofi Technologies",
  legalName: "Paxofi Technologies LTD",
  description:
    "Paxofi Technologies builds dependable digital products, software platforms and technology infrastructure for businesses and communities.",
  email: "hello@paxofi.com",
  careersUrl: "https://careers.paxofi.com",
};

/** Every public, indexable route (used by the sitemap and the E2E tests). */
export const PUBLIC_ROUTES = ["/", "/about", "/services", "/products", "/industries", "/insights", "/resources", "/careers", "/contact", "/privacy", "/terms"] as const;

/** Social share image (1200×630, Open Graph and Twitter/X large card). */
export const SHARE_IMAGE = {
  url: "/og-image.png",
  width: 1200,
  height: 630,
  alt: "Paxofi Technologies — Technology for a Brighter Tomorrow.",
};

/** Canonical site origin without a trailing slash. Inlined at build time. */
export function siteUrl(env: Record<string, string | undefined> = process.env): string {
  return (env.NEXT_PUBLIC_SITE_URL?.trim() || "https://corporate.paxofi.com").replace(/\/+$/, "");
}

/** Per-page metadata: title, description, canonical URL and Open Graph/Twitter cards. */
export function pageMetadata(title: string, description: string, path: string): Metadata {
  return {
    title,
    description,
    alternates: { canonical: path },
    openGraph: { title: `${title} | ${SITE.name}`, description, url: path, siteName: SITE.name, type: "website", images: [SHARE_IMAGE] },
    twitter: { card: "summary_large_image", title: `${title} | ${SITE.name}`, description, images: [SHARE_IMAGE.url] },
  };
}

/** schema.org Organization data for search engines (JSON-LD). */
export function organizationJsonLd(base: string = siteUrl()) {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: SITE.legalName,
    alternateName: SITE.name,
    url: base,
    email: SITE.email,
    description: SITE.description,
  };
}
