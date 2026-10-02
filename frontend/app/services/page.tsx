import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Services",
  "Software and web engineering, APIs, product strategy, digital transformation, cloud foundations and digital growth from Paxofi Technologies.",
  "/services",
);

const services = [
  "Software & Web Engineering",
  "API & Platform Development",
  "Product Strategy & Prototyping",
  "Digital Transformation",
  "Cloud & Infrastructure Foundations",
  "Digital Marketing & Growth",
];

export default function Services() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">CAPABILITIES</span>
        <h1>From strategy to software.</h1>
        <div className="grid-2 section-body">
          {services.map((title, index) => (
            <article className="feature" key={title}>
              <div className="number" aria-hidden="true">0{index + 1}</div>
              <h2 className="card-title">{title}</h2>
              <p>Structured delivery focused on maintainability, measurable outcomes and a clear path to production.</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
