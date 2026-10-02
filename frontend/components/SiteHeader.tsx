"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import Logo from "@/components/Logo";
import { NAV_LINKS } from "@/lib/site";

function isCurrent(pathname: string, href: string): boolean {
  return pathname === href || pathname.startsWith(`${href}/`);
}

export default function SiteHeader() {
  const pathname = usePathname() ?? "/";
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
          {NAV_LINKS.map(({ href, label }) => (
            <Link key={href} href={href} aria-current={isCurrent(pathname, href) ? "page" : undefined} onClick={close}>
              {label}
            </Link>
          ))}
          <Link
            href="/contact"
            className="nav-cta"
            aria-current={isCurrent(pathname, "/contact") ? "page" : undefined}
            onClick={close}
          >
            Talk to us
          </Link>
        </nav>
      </div>
    </header>
  );
}
