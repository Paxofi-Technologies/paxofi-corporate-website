"use client";

import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";

/**
 * Cookieless page-view count (decision D-014). Sends the page path, the
 * referring site on the first page of a visit, and the screen width; nothing is
 * stored in the browser. Visitors with Do Not Track or Global Privacy Control
 * switched on are not counted. A simple, fire-and-forget request: no cookies,
 * no credentials, and the page never waits for it.
 */
export function PageviewBeacon({ apiBase }: { apiBase?: string }) {
  const pathname = usePathname();
  const first = useRef(true);

  useEffect(() => {
    const entry = first.current;
    first.current = false;
    if (!apiBase || optedOut()) return;
    const body = JSON.stringify({
      path: pathname,
      entry,
      referrer: entry ? document.referrer : "",
      width: Math.round(window.innerWidth),
    });
    fetch(`${apiBase.replace(/\/+$/, "")}/analytics/pageview`, {
      method: "POST",
      mode: "no-cors",
      credentials: "omit",
      keepalive: true,
      headers: { "Content-Type": "text/plain;charset=UTF-8" },
      body,
    }).catch(() => undefined);
  }, [pathname, apiBase]);

  return null;
}

function optedOut(): boolean {
  const nav = navigator as Navigator & { globalPrivacyControl?: boolean };
  return nav.doNotTrack === "1" || nav.globalPrivacyControl === true;
}
