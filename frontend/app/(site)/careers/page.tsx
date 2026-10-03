import { ArrowUpRight, GraduationCap, HeartHandshake, Users } from "lucide-react";
import { IconCard, PageHero, SectionHead } from "@/components/Sections";
import { pageCopy } from "@/lib/page-copy-server";
import { SITE, pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("careers");
  return pageMetadata("Careers", t.meta_description, "/careers");
}

export default async function Careers() {
  const t = await pageCopy("careers");
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.why_eyebrow} title={t.why_title} />
          <div className="grid grid-3">
            <IconCard icon={Users} title={t.reason_1_title}>
              {t.reason_1_text}
            </IconCard>
            <IconCard icon={GraduationCap} title={t.reason_2_title} tone="teal">
              {t.reason_2_text}
            </IconCard>
            <IconCard icon={HeartHandshake} title={t.reason_3_title} tone="purple">
              {t.reason_3_text}
            </IconCard>
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <div className="spotlight">
            <div>
              <span className="eyebrow eyebrow--on-dark">{t.fellowship_eyebrow}</span>
              <h2>{t.fellowship_title}</h2>
              <p>{t.fellowship_text}</p>
            </div>
            <a className="button button--light" href={SITE.careersUrl} rel="noopener">
              {t.fellowship_button} <ArrowUpRight size={18} aria-hidden="true" />
            </a>
          </div>
          <p className="page-note">
            {t.note}{" "}
            <a href={SITE.careersUrl} rel="noopener">career.paxofi.com</a>.
          </p>
        </div>
      </section>
    </>
  );
}
