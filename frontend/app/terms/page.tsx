import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Terms",
  "Terms for using the Paxofi Technologies website.",
  "/terms",
);

export default function Terms() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">TERMS</span>
        <h1>Website terms.</h1>
        <p className="hero-copy">
          This website provides general information about Paxofi Technologies, its products and capabilities.
          Product-specific terms, contracts and service conditions govern any formal engagement.
        </p>
      </div>
    </section>
  );
}
