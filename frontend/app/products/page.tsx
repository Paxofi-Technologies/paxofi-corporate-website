import { Cog, ShieldCheck } from "lucide-react";
import { CtaBand, PageHero } from "@/components/Sections";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Products",
  "Paxofi Pay, dependable digital payments infrastructure, and the Paxofi Core Framework for maintainable PHP systems.",
  "/products",
);

const products = [
  {
    icon: ShieldCheck,
    label: "Paxofi Product",
    name: "Paxofi Pay",
    summary: "Digital payments infrastructure designed around reliability, transaction certainty, transparency, recovery and trust.",
    points: ["Transaction certainty", "Transparency and traceability", "Recovery built in"],
  },
  {
    icon: Cog,
    label: "Paxofi Technology",
    name: "Paxofi Core Framework",
    summary: "An independent PHP application framework for maintainable internal, client, SaaS and API systems.",
    points: ["Layered, testable architecture", "Secure HTTP and data foundations", "Built for long-lived systems"],
  },
];

export default function Products() {
  return (
    <>
      <PageHero
        eyebrow="Products"
        title="Building our own technology, too."
        intro="Alongside client work, we build and operate products that put our principles into practice."
      />

      <section className="section">
        <div className="container grid grid-2">
          {products.map(({ icon: Icon, label, name, summary, points }) => (
            <article className="product-card product-card--large" key={name}>
              <span className="product-icon" aria-hidden="true">
                <Icon size={28} strokeWidth={1.75} />
              </span>
              <span className="product-label">{label}</span>
              <h2>{name}</h2>
              <p>{summary}</p>
              <ul className="check-list">
                {points.map((point) => (
                  <li key={point}>{point}</li>
                ))}
              </ul>
            </article>
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
