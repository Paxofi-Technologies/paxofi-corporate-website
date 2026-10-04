"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import Logo from "@/components/Logo";
import { type PageCopy, defaultCopy, siteLinks } from "@/lib/page-copy";

function isCurrent(pathname: string, href: string): boolean {
  if (!href.startsWith("/")) return false;
  const path = href.split(/[?#]/)[0];
  return path === "/" ? pathname === "/" : pathname === path || pathname.startsWith(`${path}/`);
}

/** A page on this site, or another site (opened normally, without passing on this page's address). */
function MenuLink({ href, current, className, onClick, children }: { href: string; current: boolean; className?: string; onClick: () => void; children: React.ReactNode }) {
  if (!href.startsWith("/")) {
    return <a href={href} className={className} rel="noopener" onClick={onClick}>{children}</a>;
  }
  return <Link href={href} className={className} aria-current={current ? "page" : undefined} onClick={onClick}>{children}</Link>;
}

/** The main menu; its links and button come from the staff area (Content → Page text → Menu and footer, D-015). */
export default function SiteHeader({ copy = defaultCopy("site") }: { copy?: PageCopy }) {
  const pathname = usePathname() ?? "/";
  const { menu, button } = siteLinks(copy);
  // The menu remembers the page it was opened on, so it is closed on any other
  // page: navigating (by link or browser back/forward) closes it without an effect.
  const [openOn, setOpenOn] = useState<string | null>(null);
  const open = openOn === pathname;
  const close = () => setOpenOn(null);

  // Escape closes the menu.
  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpenOn(null);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <header className="site-header">
      <div className="container nav">
        <Link href="/" className="brand" aria-label="Paxofi Technologies — home">
          <Logo />
        </Link>
        <button
          type="button"
          className="menu-toggle"
          aria-expanded={open}
          aria-controls="primary-nav"
          onClick={() => setOpenOn(open ? null : pathname)}
        >
          <span className="menu-icon" aria-hidden="true" />
          <span className="visually-hidden">{open ? "Close menu" : "Open menu"}</span>
        </button>
        <nav id="primary-nav" aria-label="Main" data-open={open}>
          {menu.map(({ href, label }) => (
            <MenuLink key={`${href}|${label}`} href={href} current={isCurrent(pathname, href)} onClick={close}>
              {label}
            </MenuLink>
          ))}
          <MenuLink href={button.href} className="nav-cta" current={isCurrent(pathname, button.href)} onClick={close}>
            {button.label}
          </MenuLink>
        </nav>
      </div>
    </header>
  );
}
