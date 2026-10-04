import Link from "next/link";
import {
  ArrowUpRight, BookOpen, BriefcaseBusiness, CheckCircle2, ClipboardCheck, Code2, Compass, GraduationCap, Mail, PenTool,
  Rocket, Search, Sparkles, Target, TrendingUp, UserCog, UserPlus, Users,
} from "lucide-react";
import { IconCard, PageHero, SectionHead } from "@/components/Sections";
import { pageCopy } from "@/lib/page-copy-server";
import { SITE, pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("careers");
  return pageMetadata("Careers", t.meta_description, "/careers");
}

// Icons stay fixed; every word is edited in the staff area (D-015). Applications
// are made on careers.paxofi.com; the corporate site has no application form.
const reasons = [Rocket, Users, BookOpen, Code2, Target, TrendingUp];
const audiences = [GraduationCap, Compass, UserPlus, BriefcaseBusiness];
const processIcons = [Search, PenTool, ClipboardCheck, Users, CheckCircle2, UserCog, Rocket];
const tones = ["blue", "sky", "teal", "purple", "blue", "teal"] as const;

export default async function Careers() {
  const t = await pageCopy("careers");
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro}>
        <div className="actions">
          <a className="button button--primary" href={SITE.careersUrl} rel="noopener">
            {t.hero_primary_button} <ArrowUpRight size={18} aria-hidden="true" />
          </a>
          <Link className="button button--outline" href="/about">{t.hero_secondary_button}</Link>
        </div>
      </PageHero>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.why_eyebrow} title={t.why_title} />
          <div className="grid grid-3">
            {reasons.map((icon, i) => (
              <IconCard key={i} icon={icon} title={t[`reason_${i + 1}_title`]} tone={tones[i]}>
                {t[`reason_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow={t.join_eyebrow} title={t.join_title} />
          <div className="grid grid-4">
            {audiences.map((icon, i) => (
              <IconCard key={i} icon={icon} title={t[`join_${i + 1}_title`]} tone={tones[i]}>
                {t[`join_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
          <p className="page-note">{t.join_note}</p>
        </div>
      </section>

      <section className="section" id="opportunities">
        <div className="container grid grid-2 careers-split">
          <div>
            <SectionHead eyebrow={t.roles_eyebrow} title={t.roles_title} />
            <ul className="role-list">
              {[1, 2, 3, 4, 5, 6, 7].map((i) => (
                <li key={i}>
                  <span>{t[`role_${i}`]}</span>
                  <span className="role-meta">Remote · PIF</span>
                </li>
              ))}
            </ul>
            <p className="page-note">
              <a href={SITE.careersUrl} rel="noopener">{t.roles_link} <ArrowUpRight size={16} aria-hidden="true" /></a>
            </p>
          </div>
          <aside className="pif-panel" aria-labelledby="pif-heading">
            <span className="icon-badge icon-badge--teal" aria-hidden="true">
              <Sparkles size={24} strokeWidth={1.75} />
            </span>
            <span className="eyebrow">{t.pif_eyebrow}</span>
            <h2 id="pif-heading">{t.pif_title}</h2>
            <p>{t.pif_text}</p>
            <ul className="check-list check-list--light">
              {[1, 2, 3, 4, 5].map((i) => <li key={i}>{t[`pif_point_${i}`]}</li>)}
            </ul>
            <a className="button button--primary" href={SITE.careersUrl} rel="noopener">
              {t.pif_button} <ArrowUpRight size={18} aria-hidden="true" />
            </a>
          </aside>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow={t.pif_eyebrow} title={t.journey_title} intro={t.journey_intro} />
          <ol className="journey">
            {[1, 2, 3, 4, 5].map((i) => (
              <li key={i}>
                <span className="journey-number" aria-hidden="true">{String(i).padStart(2, "0")}</span>
                <h3>{t[`journey_${i}_title`]}</h3>
                <p>{t[`journey_${i}_text`]}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.roles_eyebrow} title={t.process_title} intro={t.process_intro} />
          <ol className="process">
            {processIcons.map((Icon, i) => (
              <li key={i}>
                <span className="process-icon" aria-hidden="true"><Icon size={22} strokeWidth={1.75} /></span>
                <span className="process-number" aria-hidden="true">{String(i + 1).padStart(2, "0")}</span>
                <span className="process-label">{t[`process_${i + 1}`]}</span>
              </li>
            ))}
          </ol>
          <div className="experience-panel">
            <h3>{t.experience_title}</h3>
            <ul className="check-list check-list--light check-list--columns">
              {[1, 2, 3, 4, 5].map((i) => <li key={i}>{t[`experience_${i}`]}</li>)}
            </ul>
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container grid faq-grid">
          <div className="faq-panel">
            <h2>{t.faq_title}</h2>
            <div className="faq-list">
              {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((i) => (
                <details key={i}>
                  <summary>{t[`faq_${i}_q`]}</summary>
                  <p>{t[`faq_${i}_a`]}</p>
                </details>
              ))}
            </div>
          </div>
          <aside className="feature-panel" aria-labelledby="equal-heading">
            <span className="icon-badge icon-badge--blue" aria-hidden="true">
              <Users size={24} strokeWidth={1.75} />
            </span>
            <h2 id="equal-heading">{t.equal_title}</h2>
            <p>{t.equal_text}</p>
          </aside>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="spotlight">
            <div>
              <h2>{t.cta_title}</h2>
              <p>{t.cta_text}</p>
              <p className="spotlight-contact">
                <Mail size={16} aria-hidden="true" /> {t.cta_contact} <a href="mailto:hr@paxofi.com">hr@paxofi.com</a>
              </p>
            </div>
            <a className="button button--light" href={SITE.careersUrl} rel="noopener">
              {t.cta_button} <ArrowUpRight size={18} aria-hidden="true" />
            </a>
          </div>
        </div>
      </section>
    </>
  );
}
