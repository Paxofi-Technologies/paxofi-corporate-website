import type { Metadata } from "next";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { PageHero } from "@/components/Sections";

export const metadata: Metadata = { title: "Page not found", robots: { index: false, follow: true } };

export default function NotFound() {
  return (
    <PageHero eyebrow="404" title="Page not found." intro="The page you requested does not exist or has moved.">
      <div className="actions">
        <Link className="button button--primary" href="/">
          <ArrowLeft size={18} aria-hidden="true" /> Return home
        </Link>
        <Link className="button button--outline" href="/contact">Contact us</Link>
      </div>
    </PageHero>
  );
}
