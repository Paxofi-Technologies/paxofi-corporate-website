"use client";

import Link from "next/link";
import { useEffect } from "react";

export default function ErrorPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    // The digest links this message to the server log entry without exposing details.
    console.error("Page error", error.digest ?? "");
  }, [error]);

  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">SOMETHING WENT WRONG</span>
        <h1>We couldn&apos;t load this page.</h1>
        <p className="hero-copy">Please try again. If it keeps happening, email hello@paxofi.com.</p>
        <div className="actions">
          <button type="button" className="button primary" onClick={reset}>Try again</button>
          <Link className="button secondary" href="/">Return home</Link>
        </div>
      </div>
    </section>
  );
}
