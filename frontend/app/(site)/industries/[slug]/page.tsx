import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { cache } from "react";
import { ArrowRight } from "lucide-react";
import { CatalogIcon, StatusPill } from "@/components/CatalogCards";
import { CtaBand } from "@/components/Sections";
import { loadCatalog, paragraphs, relatedHref } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

type Props = { params: Promise<{ slug: string }> };

// The whole list is small (ten sectors); loading it once per request also gives the built-in copy if the API is down.
const industry = cache(async (slug: string) => (await loadCatalog("industries", resolveApiBase(process.env))).find((item) => item.slug === slug) ?? null);

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const found = await industry((await params).slug);
  if (found === null) return { title: "Industries" };
  const base = pageMetadata(found.name, found.summary, `/industries/${found.slug}`);
  return found.image
    ? { ...base, openGraph: { ...base.openGraph, images: [{ url: found.image.src, width: found.image.width, height: found.image.height, alt: found.image.alt }] } }
    : base;
}

/** One industry: what Paxofi can build for it and the related services and products (SRS 13.8, D-022). */
export default async function IndustryPage({ params }: Props) {
  const [found, t] = await Promise.all([industry((await params).slug), pageCopy("industries")]);
  if (found === null) notFound();
  const related = found.related ?? [];

  return (
    <>
      <section className="page-hero">
        <div className="container page-hero-inner">
          <p className="careers-back"><Link href="/industries">← {t.hero_eyebrow}</Link></p>
          <span className="icon-badge icon-badge--blue industry-hero__icon" aria-hidden="true">
            <CatalogIcon name={found.icon} size={28} />
          </span>
          <h1>{found.name}</h1>
          <p className="lead">{found.summary}</p>
        </div>
      </section>

      <section className="section">
        <div className="container industry-detail">
          <div className="prose">
            {paragraphs(found.description).map((paragraph) => (
              <p key={paragraph}>{paragraph}</p>
            ))}
            {found.image && (
              // eslint-disable-next-line @next/next/no-img-element
              <img className="article-image" src={found.image.src} alt={found.image.alt} width={found.image.width} height={found.image.height} loading="lazy" decoding="async" />
            )}
          </div>
          {found.points.length > 0 && (
            <aside className="feature-panel industry-points" aria-labelledby="industry-points">
              <h2 id="industry-points">{t.points_heading}</h2>
              <ul className="check-list">
                {found.points.map((point) => (
                  <li key={point}>{point}</li>
                ))}
              </ul>
            </aside>
          )}
        </div>
      </section>

      {related.length > 0 && (
        <section className="section section--tint" aria-labelledby="industry-related">
          <div className="container">
            <h2 id="industry-related" className="section-title">{t.related_heading}</h2>
            <ul className="grid grid-3 related-grid">
              {related.map((item) => (
                <li key={`${item.type}:${item.slug}`} className="icon-card related-card">
                  <span className="icon-badge icon-badge--blue" aria-hidden="true">
                    <CatalogIcon name={item.icon} size={24} />
                  </span>
                  <p className="related-card__type">
                    {item.type === "product" ? "Product" : "Service"}
                    {item.type === "product" && <StatusPill status={item.status} />}
                  </p>
                  <h3>
                    <Link href={relatedHref(item)} className="card-link">{item.name}</Link>
                  </h3>
                  <p>{item.summary}</p>
                  <span className="industry-card__more" aria-hidden="true">
                    View <ArrowRight size={16} strokeWidth={2} />
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
