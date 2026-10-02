import Link from "next/link";
import { NAV_LINKS, SITE } from "@/lib/site";

export default function SiteFooter() {
  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div>
          <div className="brand footer-brand">
            <span className="brand-mark" aria-hidden="true">P</span>
            <span>PAXOFI</span>
          </div>
          <p>Technology that moves ideas into dependable digital products.</p>
        </div>
        <nav aria-label="Footer">
          <strong>Explore</strong>
          {NAV_LINKS.map(({ href, label }) => (
            <Link key={href} href={href}>{label}</Link>
          ))}
        </nav>
        <div>
          <strong>Contact</strong>
          <a href={`mailto:${SITE.email}`}>{SITE.email}</a>
          <Link href="/contact">Start a conversation</Link>
          <Link href="/privacy">Privacy</Link>
          <Link href="/terms">Terms</Link>
        </div>
      </div>
      <div className="container footer-bottom">
        © {new Date().getFullYear()} {SITE.legalName}. All rights reserved.
      </div>
    </footer>
  );
}
