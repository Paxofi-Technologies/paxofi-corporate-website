import type { Metadata } from "next";
import { headers } from "next/headers";
import Link from "next/link";
import { ArrowUpRight, Mail } from "lucide-react";
import Logo from "@/components/Logo";
import { RECRUITMENT_EMAIL, careersSiteUrl } from "@/lib/careers";
import { SHARE_IMAGE, SITE, siteUrl } from "@/lib/site";
import { isStaging } from "@/lib/staging";

const DESCRIPTION =
  "Join the Paxofi Innovation Fellowship (PIF 2026): remote, part-time roles in engineering, design, project management and HR, with real projects, mentorship and a recognised record of your work.";

/** careers.paxofi.com (D-018): its own address, title and search settings, read at run time. */
export function generateMetadata(): Metadata {
  return {
    metadataBase: new URL(careersSiteUrl()),
    title: { default: "Careers at Paxofi — Paxofi Innovation Fellowship", template: "%s | Paxofi Careers" },
    description: DESCRIPTION,
    alternates: { canonical: "/" },
    openGraph: { siteName: "Paxofi Careers", type: "website", url: "/", title: "Careers at Paxofi", description: DESCRIPTION, images: [SHARE_IMAGE] },
    twitter: { card: "summary_large_image", title: "Careers at Paxofi", description: DESCRIPTION, images: [SHARE_IMAGE.url] },
    robots: isStaging() ? { index: false, follow: false } : { index: true, follow: true },
  };
}

/** Careers site chrome: its own header and footer, linking back to the corporate website. */
export default async function CareersLayout({ children }: { children: React.ReactNode }) {
  const nonce = (await headers()).get("x-nonce") ?? undefined;
  const corporate = siteUrl();
  return (
    <>
      <a className="skip-link" href="#main">Skip to content</a>
      <header className="site-header">
        <div className="container nav careers-nav">
          <Link href="/" className="brand" aria-label="Paxofi Careers — home">
            <Logo />
            <span className="careers-badge">Careers</span>
          </Link>
          <nav aria-label="Careers">
            <Link href="/#roles">Open roles</Link>
            <Link href="/#process">How we hire</Link>
            <Link href="/#faq">FAQ</Link>
            <a href={corporate} rel="noopener">
              paxofi.com <ArrowUpRight size={14} aria-hidden="true" />
            </a>
          </nav>
        </div>
      </header>
      <main id="main" tabIndex={-1}>{children}</main>
      <footer className="footer">
        <div className="container careers-footer">
          <div>
            <Logo tone="dark" />
            <p>The Paxofi Innovation Fellowship is how people join Paxofi Technologies: remote, part-time and built on real work.</p>
          </div>
          <ul>
            <li><Link href="/#roles">Open roles</Link></li>
            <li><Link href="/privacy">Applicant privacy notice</Link></li>
            <li><a href={`${corporate}/about`} rel="noopener">About Paxofi</a></li>
            <li><a href={`mailto:${RECRUITMENT_EMAIL}`}><Mail size={14} aria-hidden="true" /> {RECRUITMENT_EMAIL}</a></li>
          </ul>
        </div>
        <div className="container footer-bottom">
          <p>© {new Date().getFullYear()} {SITE.legalName}. All rights reserved.</p>
        </div>
      </footer>
      <script
        type="application/ld+json"
        nonce={nonce}
        // Static data only; never interpolate user input here.
        dangerouslySetInnerHTML={{ __html: JSON.stringify({ "@context": "https://schema.org", "@type": "Organization", name: SITE.legalName, url: corporate, email: RECRUITMENT_EMAIL }) }}
      />
    </>
  );
}
