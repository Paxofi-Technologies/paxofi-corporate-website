import type { MetadataRoute } from "next";
import { loadArticles } from "@/lib/articles";
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
  // Published articles (D-021): up to the newest 150.
  const articles = [];
  for (let page = 1; page <= 5; page++) {
    const result = await loadArticles(resolveApiBase(process.env), { page, perPage: 30 });
    if (!result) break;
    articles.push(...result.items);
    if (page >= result.totalPages) break;
  }
  return [
    ...PUBLIC_ROUTES.map((path) => ({ url: path === "/" ? base : base + path })),
    ...articles.map((a) => ({ url: `${base}/insights/${a.slug}`, lastModified: a.updatedAt })),
  ];
}
