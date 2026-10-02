import { SITE, pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Careers",
  "Build with Paxofi: opportunities for engineers, designers, product thinkers, marketers and operators, including the Paxofi Innovation Fellowship.",
  "/careers",
);

export default function Careers() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">CAREERS</span>
        <h1>Build with Paxofi.</h1>
        <p className="hero-copy">
          We welcome engineers, designers, product thinkers, marketers and operators who want to learn, contribute and
          ship useful technology.
        </p>
        <div className="feature section-body">
          <h2 className="card-title">Paxofi Innovation Fellowship</h2>
          <p>Our fellowship creates practical opportunities to learn through real product and technology work.</p>
          <a className="button primary" href={SITE.careersUrl} rel="noopener">
            View opportunities
          </a>
        </div>
      </div>
    </section>
  );
}
