import { PageHero } from "@/components/Sections";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Terms",
  "Terms for using the Paxofi Technologies website.",
  "/terms",
);

export default function Terms() {
  return (
    <>
      <PageHero
        eyebrow="Terms"
        title="Website terms."
        intro="This website provides general information about Paxofi Technologies, its products and capabilities. Product-specific terms, contracts and service conditions govern any formal engagement."
      />
      <section className="section">
        <div className="container prose">
          <h2>Using this website</h2>
          <p>
            Content on this website is provided for general information. We work to keep it accurate and current, but
            it does not form an offer or a contract.
          </p>
          <h2>Contact</h2>
          <p>
            Questions about these terms can be sent to <a href="mailto:hello@paxofi.com">hello@paxofi.com</a>.
          </p>
        </div>
      </section>
    </>
  );
}
