import { Cloud, CodeXml, Megaphone, Network, Rocket, Workflow } from "lucide-react";
import { CtaBand, IconCard, PageHero, SectionHead } from "@/components/Sections";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Services",
  "Software and web engineering, APIs, product strategy, digital transformation, cloud foundations and digital growth from Paxofi Technologies.",
  "/services",
);

const services = [
  { icon: CodeXml, title: "Software & Web Engineering", text: "Web platforms and business applications engineered for reliability, security and maintainability." },
  { icon: Network, title: "API & Platform Development", text: "Well-documented APIs and platform services that connect products, partners and data." },
  { icon: Rocket, title: "Product Strategy & Prototyping", text: "Clarify the problem, shape the product and validate it with working prototypes." },
  { icon: Workflow, title: "Digital Transformation", text: "Modernise processes and systems with practical, measurable steps." },
  { icon: Cloud, title: "Cloud & Infrastructure Foundations", text: "Deployment, hosting, monitoring and recovery foundations that keep services running." },
  { icon: Megaphone, title: "Digital Marketing & Growth", text: "Web presence, content and digital channels that help the right people find you." },
];

const process = [
  { step: "01", title: "Discover", text: "We understand your goals, users and constraints before writing code." },
  { step: "02", title: "Build", text: "We design and engineer in small, tested increments you can review." },
  { step: "03", title: "Operate", text: "We deploy, monitor and support what we build." },
  { step: "04", title: "Scale", text: "We improve and extend the product as your needs grow." },
];

export default function Services() {
  return (
    <>
      <PageHero
        eyebrow="Capabilities"
        title="From strategy to software."
        intro="Structured delivery focused on maintainability, measurable outcomes and a clear path to production."
      />

      <section className="section">
        <div className="container grid grid-3">
          {services.map(({ icon, title, text }) => (
            <IconCard key={title} icon={icon} title={title}>
              {text}
            </IconCard>
          ))}
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow="How we deliver" title="A clear path from idea to operation." />
          <ol className="steps steps--4">
            {process.map(({ step, title, text }) => (
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
