import type { MetadataRoute } from "next";
import { careersSiteUrl, isCareersSite, loadRoles } from "@/lib/careers";
import { resolveApiBase } from "@/lib/contact";
import { PUBLIC_ROUTES, siteUrl } from "@/lib/site";
import { isStaging } from "@/lib/staging";

// Read per request: the staging copy (D-013) lists nothing.
export const dynamic = "force-dynamic";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  if (isStaging()) return [];
  if (isCareersSite()) {
    // careers.paxofi.com (D-018): home, privacy notice and each open role.
    const base = careersSiteUrl();
    const roles = (await loadRoles(resolveApiBase(process.env))) ?? [];
    return [{ url: base }, ...roles.map((role) => ({ url: `${base}/roles/${role.slug}` })), { url: `${base}/privacy` }];
  }
  const base = siteUrl();
  return PUBLIC_ROUTES.map((path) => ({ url: path === "/" ? base : base + path }));
}
