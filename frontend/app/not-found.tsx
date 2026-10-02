import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = { title: "Page not found", robots: { index: false, follow: true } };

export default function NotFound() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">404</span>
        <h1>Page not found.</h1>
        <p className="hero-copy">The page you requested does not exist.</p>
        <Link className="button primary" href="/">Return home</Link>
      </div>
    </section>
  );
}
