import type { MetadataRoute } from "next";
import { careersSiteUrl, isCareersSite } from "@/lib/careers";
import { siteUrl } from "@/lib/site";
import { isStaging } from "@/lib/staging";

// Read per request: the same build serves the live site and the staging copy (D-013).
export const dynamic = "force-dynamic";

export default function robots(): MetadataRoute.Robots {
  if (isStaging()) return { rules: { userAgent: "*", disallow: "/" } };
  const base = isCareersSite() ? careersSiteUrl() : siteUrl();
  return { rules: { userAgent: "*", allow: "/", disallow: "/admin" }, sitemap: `${base}/sitemap.xml` };
}
