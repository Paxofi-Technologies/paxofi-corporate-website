import { ChartColumn, Compass, Globe, Lightbulb, Target, Users } from "lucide-react";
import { CtaBand, IconCard, PageHero, SectionHead } from "@/components/Sections";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "About",
  "Paxofi Technologies LTD combines product thinking, engineering discipline and operational execution to build dependable technology.",
  "/about",
);

const values = [
  { icon: Users, title: "People First", text: "We invest in people.", tone: "blue" },
  { icon: Lightbulb, title: "Innovation Always", text: "We turn ideas into impact.", tone: "sky" },
  { icon: ChartColumn, title: "Real Solutions", text: "We solve real problems.", tone: "teal" },
  { icon: Globe, title: "A Brighter Tomorrow", text: "We create lasting possibilities.", tone: "purple" },
] as const;

export default function About() {
  return (
    <>
      <PageHero
        eyebrow="About Paxofi"
        title="Technology with a long-term view."
        intro="Paxofi Technologies LTD is a technology company focused on building software, digital products and practical infrastructure. We combine product thinking, engineering discipline and operational execution."
      />

      <section className="section">
        <div className="container grid grid-2">
          <article className="feature-panel">
            <span className="icon-badge icon-badge--blue" aria-hidden="true">
              <Target size={24} strokeWidth={1.75} />
            </span>
            <h2>Our mission</h2>
            <p>Make dependable technology accessible to businesses and communities that need to move forward.</p>
          </article>
          <article className="feature-panel">
            <span className="icon-badge icon-badge--teal" aria-hidden="true">
              <Compass size={24} strokeWidth={1.75} />
            </span>
            <h2>Our principles</h2>
            <p>Build clearly. Protect users. Measure what matters. Ship useful products. Improve continuously.</p>
          </article>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead
            eyebrow="Our values"
            title="People, products and possibilities."
            intro="Four values shape every product we ship and every partnership we build."
          />
          <div className="grid grid-4">
            {values.map(({ icon, title, text, tone }) => (
              <IconCard key={title} icon={icon} title={title} tone={tone}>
                {text}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <CtaBand
        title="Let's build what's next."
        text="Partner with a team that thinks about the long term from day one."
      />
    </>
  );
}
