import type { Metadata } from "next";
import { headers } from "next/headers";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import ArticleBody from "@/components/ArticleBody";
import { formatArticleDate, loadArticle, readingMinutes } from "@/lib/articles";
import { resolveApiBase } from "@/lib/contact";
import { SITE, pageMetadata, siteUrl } from "@/lib/site";

export const dynamic = "force-dynamic";

type Props = { params: Promise<{ slug: string }> };
const article = cache((slug: string) => loadArticle(resolveApiBase(process.env), slug));

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const found = await article((await params).slug);
  if (found === null || found === "missing") return { title: "News & Insights" };
  const base = pageMetadata(found.title, found.summary, `/insights/${found.slug}`);
  const images = found.image ? [{ url: found.image.src, width: found.image.width, height: found.image.height, alt: found.image.alt }] : base.openGraph?.images;
  return {
    ...base,
    openGraph: { ...base.openGraph, type: "article", publishedTime: found.publishedAt, modifiedTime: found.updatedAt, images },
    twitter: { ...base.twitter, images: found.image ? [found.image.src] : base.twitter?.images },
  };
}

/** One News & Insights article (D-021). */
export default async function ArticlePage({ params }: Props) {
  const found = await article((await params).slug);
  if (found === "missing") notFound();
  if (found === null) {
    return (
      <section className="section">
        <div className="container prose">
          <h1>Article unavailable</h1>
          <p role="alert">We could not load this article just now. Please refresh in a minute.</p>
          <p><Link href="/insights">All news and insights</Link></p>
        </div>
      </section>
    );
  }
  const nonce = (await headers()).get("x-nonce") ?? undefined;
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": found.category === "news" || found.category === "announcement" ? "NewsArticle" : "BlogPosting",
    headline: found.title,
    description: found.summary,
    datePublished: found.publishedAt,
    dateModified: found.updatedAt,
    mainEntityOfPage: `${siteUrl()}/insights/${found.slug}`,
    image: found.image ? [found.image.src] : undefined,
    author: found.authorName ? { "@type": "Person", name: found.authorName } : { "@type": "Organization", name: SITE.legalName },
    publisher: { "@type": "Organization", name: SITE.legalName, url: siteUrl() },
  };

  return (
    <article>
      <header className="page-hero">
        <div className="container page-hero-inner article-header">
          <p className="careers-back"><Link href="/insights">← News &amp; Insights</Link></p>
          <span className="eyebrow">{found.categoryLabel}</span>
          <h1>{found.title}</h1>
          <p className="lead">{found.summary}</p>
          <p className="article-meta">
            {found.authorName && <span>By {found.authorName}</span>}
            <time dateTime={found.publishedAt}>{formatArticleDate(found.publishedAt)}</time>
            <span>{readingMinutes(found.body)} min read</span>
          </p>
        </div>
      </header>
      <section className="section">
        <div className="container article-layout">
          {found.image && (
            // eslint-disable-next-line @next/next/no-img-element
            <img className="article-image" src={found.image.src} alt={found.image.alt} width={found.image.width} height={found.image.height} decoding="async" />
          )}
          <ArticleBody body={found.body} />
          <p className="article-end"><Link href="/insights">More news and insights</Link> · <Link href="/contact">Talk to us</Link></p>
        </div>
      </section>
      <script
        type="application/ld+json"
        nonce={nonce}
        // Values are JSON-encoded; "<" is escaped so no text can close the script.
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }}
      />
    </article>
  );
}
