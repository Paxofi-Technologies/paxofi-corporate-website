import { ServiceCard } from "@/components/CatalogCards";
import { CtaBand, PageHero, SectionHead } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("services");
  return pageMetadata("Services", t.meta_description, "/services");
}

// Step numbers stay fixed; their words are edited in the staff area (D-015).
const deliverySteps = ["01", "02", "03", "04"];

export default async function Services() {
  // Edited in the staff area (D-011); falls back to the built-in copy if the API cannot be read.
  const [services, t] = await Promise.all([loadCatalog("services", resolveApiBase(process.env)), pageCopy("services")]);
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container grid grid-3">
          {services.map((item) => (
            <ServiceCard key={item.slug} item={item} anchor />
          ))}
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow={t.delivery_eyebrow} title={t.delivery_title} />
          <ol className="steps steps--4">
            {deliverySteps.map((step, i) => (
              <li className="step" key={step}>
                <span className="step-number" aria-hidden="true">{step}</span>
                <h3>{t[`step_${i + 1}_title`]}</h3>
                <p>{t[`step_${i + 1}_text`]}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
