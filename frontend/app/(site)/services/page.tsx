import { ServiceCard } from "@/components/CatalogCards";
import { CtaBand, PageHero, SectionHead } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Services",
  "Software and web engineering, APIs, product strategy, digital transformation, cloud foundations and digital growth from Paxofi Technologies.",
  "/services",
);

const deliverySteps = [
  { step: "01", title: "Discover", text: "We understand your goals, users and constraints before writing code." },
  { step: "02", title: "Build", text: "We design and engineer in small, tested increments you can review." },
  { step: "03", title: "Operate", text: "We deploy, monitor and support what we build." },
  { step: "04", title: "Scale", text: "We improve and extend the product as your needs grow." },
];

export default async function Services() {
  // Edited in the staff area (D-011); falls back to the built-in copy if the API cannot be read.
  const services = await loadCatalog("services", resolveApiBase(process.env));
  return (
    <>
      <PageHero
        eyebrow="Capabilities"
        title="From strategy to software."
        intro="Structured delivery focused on maintainability, measurable outcomes and a clear path to production."
      />

      <section className="section">
        <div className="container grid grid-3">
          {services.map((item) => (
            <ServiceCard key={item.slug} item={item} />
          ))}
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow="How we deliver" title="A clear path from idea to operation." />
          <ol className="steps steps--4">
            {deliverySteps.map(({ step, title, text }) => (
              <li className="step" key={title}>
                <span className="step-number" aria-hidden="true">{step}</span>
                <h3>{title}</h3>
                <p>{text}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <CtaBand
        title="Need a capable technology partner?"
        text="Tell us about your project and we'll suggest a practical way forward."
      />
    </>
  );
}
