import type { Metadata } from "next";

/** Public site settings shared by metadata, sitemap, robots and navigation. */
export const SITE = {
  name: "Paxofi Technologies",
  legalName: "Paxofi Technologies LTD",
  description:
    "Paxofi Technologies builds dependable digital products, software platforms and technology infrastructure for businesses and communities.",
  email: "hello@paxofi.com",
  careersUrl: "https://career.paxofi.com",
};

export const NAV_LINKS = [
  { href: "/about", label: "About" },
  { href: "/services", label: "Services" },
  { href: "/products", label: "Products" },
  { href: "/careers", label: "Careers" },
] as const;

/** Every public, indexable route (used by the sitemap and the E2E tests). */
export const PUBLIC_ROUTES = ["/", "/about", "/services", "/products", "/careers", "/contact", "/privacy", "/terms"] as const;

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
    openGraph: { title: `${title} | ${SITE.name}`, description, url: path, siteName: SITE.name, type: "website" },
    twitter: { card: "summary", title: `${title} | ${SITE.name}`, description },
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
