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
  const [open, setOpen] = useState(false);
  const close = () => setOpen(false);

  // Close the mobile menu after navigating (links also close it on click, and
  // browser back/forward changes the path) and when Escape is pressed.
  useEffect(() => setOpen(false), [pathname]);
  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
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
          onClick={() => setOpen((value) => !value)}
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
