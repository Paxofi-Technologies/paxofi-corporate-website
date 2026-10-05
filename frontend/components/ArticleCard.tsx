import Link from "next/link";
import { type ArticleSummary, formatArticleDate } from "@/lib/articles";

/** One article on the News & Insights list; the whole card opens it. */
export default function ArticleCard({ article }: { article: ArticleSummary }) {
  return (
    <article className="article-card">
      {article.image && (
        // eslint-disable-next-line @next/next/no-img-element
        <img className="card-image" src={article.image.src} alt="" width={article.image.width} height={article.image.height} loading="lazy" decoding="async" />
      )}
      <p className="article-meta">
        <span className="article-category">{article.categoryLabel}</span>
        <time dateTime={article.publishedAt}>{formatArticleDate(article.publishedAt)}</time>
      </p>
      <h2><Link href={`/insights/${article.slug}`}>{article.title}</Link></h2>
      <p>{article.summary}</p>
    </article>
  );
}
