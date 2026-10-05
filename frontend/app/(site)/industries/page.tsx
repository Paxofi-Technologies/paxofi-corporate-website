import { IndustryCard } from "@/components/CatalogCards";
import { CtaBand, PageHero } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("industries");
  return pageMetadata("Industries", t.meta_description, "/industries");
}

/** The sectors Paxofi builds for (SRS 13.8, D-022); edited under Content → Industries. */
export default async function Industries() {
  // Falls back to the built-in copy (the ten SRS 4.6 sectors) if the API cannot be read.
  const [industries, t] = await Promise.all([loadCatalog("industries", resolveApiBase(process.env)), pageCopy("industries")]);

  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container grid grid-3 industry-grid">
          {industries.map((item) => (
            <IndustryCard key={item.slug} item={item} />
          ))}
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
