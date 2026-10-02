import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Products",
  "Paxofi Pay, dependable digital payments infrastructure, and the Paxofi Core Framework for maintainable PHP systems.",
  "/products",
);

const products = [
  {
    label: "PAXOFI PRODUCT",
    name: "Paxofi Pay",
    summary: "Digital payments infrastructure designed around reliability, transaction certainty, transparency, recovery and trust.",
  },
  {
    label: "PAXOFI TECHNOLOGY",
    name: "Paxofi Core Framework",
    summary: "An independent PHP application framework for maintainable internal, client, SaaS and API systems.",
  },
];

export default function Products() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">PRODUCTS</span>
        <h1>Building our own technology, too.</h1>
        <div className="grid-2 section-body">
          {products.map(({ label, name, summary }) => (
            <article className="product-card solid" key={name}>
              <span>{label}</span>
              <h2 className="card-title">{name}</h2>
              <p>{summary}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
