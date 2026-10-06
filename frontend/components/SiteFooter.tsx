import Link from "next/link";
import Logo from "@/components/Logo";
import { type PageCopy, type SiteLink, defaultCopy, siteLinks } from "@/lib/page-copy";
import { SITE } from "@/lib/site";

/** The footer; its wording and links come from the staff area (Menu and footer, D-015). Resources, Privacy and Terms stay fixed. */
export default function SiteFooter({ copy = defaultCopy("site") }: { copy?: PageCopy }) {
  const { menu, contact } = siteLinks(copy);
  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div className="footer-brand">
          <Logo tone="dark" descriptor />
          <p>{copy.footer_text}</p>
        </div>
        <nav aria-label="Footer">
          <h2 className="footer-heading">{copy.footer_explore_heading}</h2>
          <ul>
            {menu.map((link) => (
              <li key={`${link.href}|${link.label}`}>
                <FooterLink {...link} />
              </li>
            ))}
          </ul>
        </nav>
        <div>
          <h2 className="footer-heading">{copy.footer_contact_heading}</h2>
          <ul>
            <li>
              <a href={`mailto:${copy.footer_email}`}>{copy.footer_email}</a>
            </li>
            {contact.map((link) => (
              <li key={`${link.href}|${link.label}`}>
                <FooterLink {...link} />
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2 className="footer-heading">{copy.footer_company_heading}</h2>
          <ul>
            <li>
              <Link href="/resources">Resources</Link>
            </li>
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
        <span className="footer-signoff">{copy.footer_signoff}</span>
      </div>
    </footer>
  );
}

function FooterLink({ href, label }: SiteLink) {
  return href.startsWith("/") ? <Link href={href}>{label}</Link> : <a href={href} rel="noopener">{label}</a>;
}
