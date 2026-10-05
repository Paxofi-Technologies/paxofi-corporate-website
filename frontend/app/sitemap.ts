import type { MetadataRoute } from "next";
import { loadArticles } from "@/lib/articles";
import { careersSiteUrl, isCareersSite, loadRoles } from "@/lib/careers";
import { loadCatalog } from "@/lib/catalog";
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
  // Industry pages (D-022); the built-in list if the API cannot be read, as the pages themselves show.
  const industries = await loadCatalog("industries", resolveApiBase(process.env));
  return [
    ...PUBLIC_ROUTES.map((path) => ({ url: path === "/" ? base : base + path })),
    ...industries.map((i) => ({ url: `${base}/industries/${i.slug}` })),
    ...articles.map((a) => ({ url: `${base}/insights/${a.slug}`, lastModified: a.updatedAt })),
  ];
}
