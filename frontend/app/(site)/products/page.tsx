import { ProductCard } from "@/components/CatalogCards";
import { CtaBand, PageHero } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Products",
  "Paxofi Pay, dependable digital payments infrastructure, and the Paxofi Core Framework for maintainable PHP systems.",
  "/products",
);

export default async function Products() {
  // Edited in the staff area (D-011); falls back to the built-in copy if the API cannot be read.
  const products = await loadCatalog("products", resolveApiBase(process.env));

  return (
    <>
      <PageHero
        eyebrow="Products"
        title="Building our own technology, too."
        intro="Alongside client work, we build and operate products that put our principles into practice."
      />

      <section className="section">
        <div className="container grid grid-2">
          {products.map((item) => (
            <ProductCard key={item.slug} item={item} />
          ))}
        </div>
      </section>

      <CtaBand
        title="Interested in our products?"
        text="Talk to us about partnerships, early access or building on Paxofi technology."
      />
    </>
  );
}
