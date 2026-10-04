import Link from "next/link";
import {
  ArrowRight, Cloud, Code2, Eye, GraduationCap, Handshake, HeartHandshake, Lightbulb, Megaphone, MonitorSmartphone,
  PenTool, Scale, ShieldCheck, Sparkles, Target, Trophy,
} from "lucide-react";
import { CatalogIcon } from "@/components/CatalogCards";
import { CtaBand, IconCard, PageHero, SectionHead } from "@/components/Sections";
import { resolveApiBase } from "@/lib/contact";
import { loadCatalog } from "@/lib/catalog";
import { pageCopy } from "@/lib/page-copy-server";
import { pageMetadata } from "@/lib/site";

export async function generateMetadata() {
  const t = await pageCopy("about");
  return pageMetadata("About", t.meta_description, "/about");
}

// Icons and colours stay fixed; every word is edited in the staff area (D-015).
const services = [Code2, PenTool, MonitorSmartphone, Megaphone, Cloud, GraduationCap];
const values = [
  { icon: Scale, tone: "blue" },
  { icon: Lightbulb, tone: "sky" },
  { icon: Trophy, tone: "teal" },
  { icon: Handshake, tone: "purple" },
  { icon: ShieldCheck, tone: "blue" },
  { icon: HeartHandshake, tone: "teal" },
] as const;
const tones = ["blue", "sky", "teal", "purple", "blue", "teal"] as const;

export default async function About() {
  const [t, products] = await Promise.all([pageCopy("about"), loadCatalog("products", resolveApiBase(process.env))]);
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro}>
        <div className="actions">
          <Link className="button button--primary" href="/careers">
            {t.hero_primary_button} <ArrowRight size={18} aria-hidden="true" />
          </Link>
          <a className="button button--outline" href="#story">{t.hero_secondary_button}</a>
        </div>
      </PageHero>

      <section className="section">
        <div className="container split">
          <div>
            <SectionHead eyebrow={t.who_eyebrow} title={t.who_title} />
            <p className="lead-muted">{t.who_text}</p>
          </div>
          <dl className="facts">
            {[1, 2, 3, 4].map((i) => (
              <div className="fact" key={i}>
                <dt>{t[`fact_${i}_label`]}</dt>
                <dd>{t[`fact_${i}_value`]}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      <section className="section section--tint" id="story" tabIndex={-1}>
        <div className="container">
          <SectionHead eyebrow={t.story_eyebrow} title={t.story_title} />
          <ol className="timeline">
            {[1, 2, 3, 4].map((i) => (
              <li key={i}>
                <span className="timeline-label">{t[`story_${i}_label`]}</span>
                <p>{t[`story_${i}_text`]}</p>
              </li>
            ))}
          </ol>
          <div className="grid grid-2 vm-grid">
            <article className="feature-panel">
              <span className="icon-badge icon-badge--blue" aria-hidden="true">
                <Eye size={24} strokeWidth={1.75} />
              </span>
              <h2>{t.vision_title}</h2>
              <p>{t.vision_text}</p>
            </article>
            <article className="feature-panel">
              <span className="icon-badge icon-badge--teal" aria-hidden="true">
                <Target size={24} strokeWidth={1.75} />
              </span>
              <h2>{t.mission_title}</h2>
              <p>{t.mission_text}</p>
            </article>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.services_eyebrow} title={t.services_title} />
          <div className="grid grid-3">
            {services.map((icon, i) => (
              <IconCard key={i} icon={icon} title={t[`service_${i + 1}_title`]} tone={tones[i]}>
                {t[`service_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
          <p className="page-note">
            <Link href="/services">How we deliver these services <ArrowRight size={16} aria-hidden="true" /></Link>
          </p>
        </div>
      </section>

      <section className="section section--navy">
        <div className="container">
          <SectionHead eyebrow={t.products_eyebrow} title={t.products_title} intro={t.products_text} />
          <div className="grid grid-3">
            {products.map(({ slug, icon, label, name, summary }) => (
              <article className="product-card" key={slug}>
                <span className="product-icon" aria-hidden="true">
                  <CatalogIcon name={icon} size={28} />
                </span>
                {label && <span className="product-label">{label}</span>}
                <h3>{name}</h3>
                <p>{summary}</p>
              </article>
            ))}
            <article className="product-card">
              <span className="product-icon" aria-hidden="true">
                <Sparkles size={28} strokeWidth={1.75} />
              </span>
              <h3>{t.products_more_title}</h3>
              <p>{t.products_more_text}</p>
            </article>
          </div>
          <p className="page-note page-note--on-dark">
            <Link href="/products">{t.products_link} <ArrowRight size={16} aria-hidden="true" /></Link>
          </p>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.values_eyebrow} title={t.values_title} />
          <div className="grid grid-3">
            {values.map(({ icon, tone }, i) => (
              <IconCard key={i} icon={icon} title={t[`value_${i + 1}_title`]} tone={tone}>
                {t[`value_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container grid grid-2 work-grid">
          <div>
            <SectionHead eyebrow={t.work_eyebrow} title={t.work_title} />
            <p className="lead-muted">{t.work_text}</p>
            <ul className="check-list check-list--light check-list--columns">
              {[1, 2, 3, 4].map((i) => <li key={i}>{t[`work_point_${i}`]}</li>)}
            </ul>
          </div>
          <div>
            <SectionHead eyebrow={t.people_eyebrow} title={t.people_title} />
            <p className="lead-muted">{t.people_text}</p>
          </div>
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} href="/careers" />
    </>
  );
}
