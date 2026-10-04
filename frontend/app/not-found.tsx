import type { Metadata } from "next";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { PageHero } from "@/components/Sections";
import SiteFooter from "@/components/SiteFooter";
import SiteHeader from "@/components/SiteHeader";
import { pageCopy } from "@/lib/page-copy-server";

export const metadata: Metadata = { title: "Page not found", robots: { index: false, follow: true } };

// Rendered by the root layout for any unknown address, so it brings the public chrome itself.
export default async function NotFound() {
  const site = await pageCopy("site");
  return (
    <>
      <a className="skip-link" href="#main">Skip to content</a>
      <SiteHeader copy={site} />
      <main id="main" tabIndex={-1}>
        <PageHero eyebrow="404" title="Page not found." intro="The page you requested does not exist or has moved.">
          <div className="actions">
            <Link className="button button--primary" href="/">
              <ArrowLeft size={18} aria-hidden="true" /> Return home
            </Link>
            <Link className="button button--outline" href="/contact">Contact us</Link>
          </div>
        </PageHero>
      </main>
      <SiteFooter copy={site} />
    </>
  );
}
