import Link from "next/link";
import Logo from "@/components/Logo";
import { NAV_LINKS, SITE } from "@/lib/site";

export default function SiteFooter() {
  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div className="footer-brand">
          <Logo tone="dark" descriptor />
          <p>
            We build people, products and solutions that create real impact across Africa and beyond.
          </p>
        </div>
        <nav aria-label="Footer">
          <h2 className="footer-heading">Explore</h2>
          <ul>
            {NAV_LINKS.map(({ href, label }) => (
              <li key={href}>
                <Link href={href}>{label}</Link>
              </li>
            ))}
          </ul>
        </nav>
        <div>
          <h2 className="footer-heading">Contact</h2>
          <ul>
            <li>
              <a href={`mailto:${SITE.email}`}>{SITE.email}</a>
            </li>
            <li>
              <Link href="/contact">Start a conversation</Link>
            </li>
            <li>
              <a href={SITE.careersUrl} rel="noopener">career.paxofi.com</a>
            </li>
          </ul>
        </div>
        <div>
          <h2 className="footer-heading">Company</h2>
          <ul>
            <li>
              <Link href="/privacy">Privacy</Link>
            </li>
            <li>
              <Link href="/terms">Terms</Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="container footer-bottom">
        <span>
          © {new Date().getFullYear()} {SITE.legalName}. All rights reserved.
        </span>
        <span className="footer-signoff">A brighter tomorrow, together.</span>
      </div>
    </footer>
  );
}
