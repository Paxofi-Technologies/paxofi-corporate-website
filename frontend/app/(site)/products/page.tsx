import { ProductCard } from "@/components/CatalogCards";
import { CtaBand, PageHero } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("products");
  return pageMetadata("Products", t.meta_description, "/products");
}

export default async function Products() {
  // Edited in the staff area (D-011); falls back to the built-in copy if the API cannot be read.
  const [products, t] = await Promise.all([loadCatalog("products", resolveApiBase(process.env)), pageCopy("products")]);

  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container grid grid-2">
          {products.map((item) => (
            <ProductCard key={item.slug} item={item} />
          ))}
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
