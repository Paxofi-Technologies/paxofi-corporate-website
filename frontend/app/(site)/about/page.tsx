import { ChartColumn, Compass, Globe, Lightbulb, Target, Users } from "lucide-react";
import { CtaBand, IconCard, PageHero, SectionHead } from "@/components/Sections";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("about");
  return pageMetadata("About", t.meta_description, "/about");
}

// Card icons and colours stay fixed; their words are edited in the staff area (D-015).
const values = [
  { icon: Users, tone: "blue" },
  { icon: Lightbulb, tone: "sky" },
  { icon: ChartColumn, tone: "teal" },
  { icon: Globe, tone: "purple" },
] as const;

export default async function About() {
  const t = await pageCopy("about");
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container grid grid-2">
          <article className="feature-panel">
            <span className="icon-badge icon-badge--blue" aria-hidden="true">
              <Target size={24} strokeWidth={1.75} />
            </span>
            <h2>{t.mission_title}</h2>
            <p>{t.mission_text}</p>
          </article>
          <article className="feature-panel">
            <span className="icon-badge icon-badge--teal" aria-hidden="true">
              <Compass size={24} strokeWidth={1.75} />
            </span>
            <h2>{t.principles_title}</h2>
            <p>{t.principles_text}</p>
          </article>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow={t.values_eyebrow} title={t.values_title} intro={t.values_intro} />
          <div className="grid grid-4">
            {values.map(({ icon, tone }, i) => (
              <IconCard key={i} icon={icon} title={t[`value_${i + 1}_title`]} tone={tone}>
                {t[`value_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
