"use client";

import Link from "next/link";
import { useEffect } from "react";

export default function ErrorPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    // The digest links this message to the server log entry without exposing details.
    console.error("Page error", error.digest ?? "");
  }, [error]);

  return (
    <section className="page-hero">
      <div className="container page-hero-inner">
        <span className="eyebrow">Something went wrong</span>
        <h1>We couldn&apos;t load this page.</h1>
        <p className="lead">Please try again. If it keeps happening, email hello@paxofi.com.</p>
        <div className="actions">
          <button type="button" className="button button--primary" onClick={reset}>Try again</button>
          <Link className="button button--outline" href="/">Return home</Link>
        </div>
      </div>
    </section>
  );
}
