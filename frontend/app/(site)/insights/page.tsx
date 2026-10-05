import Link from "next/link";
import ArticleCard from "@/components/ArticleCard";
import { PageHero } from "@/components/Sections";
import { ARTICLE_CATEGORIES, loadArticles } from "@/lib/articles";
import { resolveApiBase } from "@/lib/contact";
import { SITE, pageMetadata } from "@/lib/site";

export const dynamic = "force-dynamic";

export const metadata = pageMetadata(
  "News & Insights",
  "News, announcements and insights from Paxofi Technologies: products, engineering and the Paxofi Innovation Fellowship.",
  "/insights",
);

type Props = { searchParams: Promise<{ category?: string; page?: string }> };

/** News & Insights (D-021): published articles, newest first, filtered by category. */
export default async function Insights({ searchParams }: Props) {
  const query = await searchParams;
  const category = ARTICLE_CATEGORIES.some((c) => c.value === query.category) ? (query.category as string) : null;
  const page = Math.min(1000, Math.max(1, Number.parseInt(query.page ?? "1", 10) || 1));
  const result = await loadArticles(resolveApiBase(process.env), { category, page });
  const href = (next: { category?: string | null; page?: number }) => {
    const params = new URLSearchParams();
    const c = next.category === undefined ? category : next.category;
    if (c) params.set("category", c);
    if (next.page && next.page > 1) params.set("page", String(next.page));
    const qs = params.toString();
    return qs ? `/insights?${qs}` : "/insights";
  };

  return (
    <>
      <PageHero eyebrow="News & Insights" title="What we are building and learning." intro="Announcements, product news and practical insights from the Paxofi team." />
      <section className="section">
        <div className="container">
          <nav className="article-filters" aria-label="Filter articles">
            <Link href={href({ category: null })} aria-current={category === null ? "page" : undefined}>All</Link>
            {ARTICLE_CATEGORIES.map((c) => (
              <Link key={c.value} href={href({ category: c.value })} aria-current={category === c.value ? "page" : undefined}>{c.label}</Link>
            ))}
          </nav>
          {result === null ? (
            <p className="form-status" data-state="error" role="alert">
              We could not load the articles just now. Please refresh in a minute, or email <a href={`mailto:${SITE.email}`}>{SITE.email}</a>.
            </p>
          ) : result.items.length === 0 ? (
            <p>No articles here yet. Please check back soon.</p>
          ) : (
            <div className="article-grid">
              {result.items.map((article) => <ArticleCard key={article.slug} article={article} />)}
            </div>
          )}
          {result && result.totalPages > 1 && (
            <nav className="article-pager" aria-label="Pages">
              {result.page > 1 && <Link href={href({ page: result.page - 1 })}>← Newer</Link>}
              <span>Page {result.page} of {result.totalPages}</span>
              {result.page < result.totalPages && <Link href={href({ page: result.page + 1 })}>Older →</Link>}
            </nav>
          )}
        </div>
      </section>
    </>
  );
}
