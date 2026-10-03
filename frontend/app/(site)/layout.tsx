import { headers } from "next/headers";
import SiteFooter from "@/components/SiteFooter";
import SiteHeader from "@/components/SiteHeader";
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
      <script
        type="application/ld+json"
        nonce={nonce}
        // Static, build-time data only; never interpolate user input here.
        dangerouslySetInnerHTML={{ __html: JSON.stringify(organizationJsonLd()) }}
      />
    </>
  );
}
