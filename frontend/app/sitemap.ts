import type { MetadataRoute } from "next";
import { PUBLIC_ROUTES, siteUrl } from "@/lib/site";
import { isStaging } from "@/lib/staging";

// Read per request: the staging copy (D-013) lists nothing.
export const dynamic = "force-dynamic";

export default function sitemap(): MetadataRoute.Sitemap {
  if (isStaging()) return [];
  const base = siteUrl();
  return PUBLIC_ROUTES.map((path) => ({ url: path === "/" ? base : base + path }));
}
