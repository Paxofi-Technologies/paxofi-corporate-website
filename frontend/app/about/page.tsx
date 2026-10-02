import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "About",
  "Paxofi Technologies LTD combines product thinking, engineering discipline and operational execution to build dependable technology.",
  "/about",
);

export default function About() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">ABOUT PAXOFI</span>
        <h1>Technology with a long-term view.</h1>
        <p className="hero-copy">
          Paxofi Technologies LTD is a technology company focused on building software, digital products and practical
          infrastructure. We combine product thinking, engineering discipline and operational execution.
        </p>
        <div className="grid-2 section-body">
          <article className="feature">
            <h2 className="card-title">Our mission</h2>
            <p>Make dependable technology accessible to businesses and communities that need to move forward.</p>
          </article>
          <article className="feature">
            <h2 className="card-title">Our principles</h2>
            <p>Build clearly. Protect users. Measure what matters. Ship useful products. Improve continuously.</p>
          </article>
        </div>
      </div>
    </section>
  );
}
