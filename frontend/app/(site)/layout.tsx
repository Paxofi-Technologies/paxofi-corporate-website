import { headers } from "next/headers";
import SiteFooter from "@/components/SiteFooter";
import SiteHeader from "@/components/SiteHeader";
import { PageviewBeacon } from "@/components/PageviewBeacon";
import { resolveApiBase } from "@/lib/contact";
import { organizationJsonLd } from "@/lib/site";

/** Public website chrome: skip link, header, main landmark, footer and Organization JSON-LD. */
export default async function SiteLayout({ children }: { children: React.ReactNode }) {
  // Per-request CSP nonce from proxy.ts (absent in development).
  const nonce = (await headers()).get("x-nonce") ?? undefined;
  return (
    <>
      <a className="skip-link" href="#main">Skip to content</a>
      <SiteHeader />
      <main id="main" tabIndex={-1}>{children}</main>
      <SiteFooter />
      <PageviewBeacon apiBase={absoluteApiBase()} />
      <script
        type="application/ld+json"
        nonce={nonce}
        // Static, build-time data only; never interpolate user input here.
        dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd()) }}
      />
    </>
  );
}

/** Page views are sent only to an absolute API address (D-014); none in development without one. */
function absoluteApiBase(): string | undefined {
  const base = resolveApiBase(process.env);
  return base && /^https?:\/\//.test(base) ? base : undefined;
}
