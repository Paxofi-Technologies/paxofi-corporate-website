import Link from "next/link";
import { ArrowRight, CheckCircle2, ClipboardCheck, Mail, PenTool, Rocket, Search, Sparkles, UserCog, Users } from "lucide-react";
import { SectionHead } from "@/components/Sections";
import { RECRUITMENT_EMAIL, loadRoles } from "@/lib/careers";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";

// Open roles come from the API on each request (edited in the staff area, D-018);
// the rest of the wording is the Careers page text (Content → Page text → Careers).
export const dynamic = "force-dynamic";

const processIcons = [Search, PenTool, ClipboardCheck, Users, CheckCircle2, UserCog, Rocket];

export default async function CareersHome() {
  const [t, roles] = await Promise.all([pageCopy("careers"), loadRoles(resolveApiBase(process.env))]);
  return (
    <>
      <section className="page-hero careers-hero">
        <div className="container page-hero-inner">
          <span className="eyebrow">{t.pif_eyebrow}</span>
          <h1>{t.pif_title}</h1>
          <p className="lead">{t.pif_text}</p>
          <ul className="check-list check-list--light careers-hero-points">
            {[1, 2, 3, 4, 5].map((i) => <li key={i}>{t[`pif_point_${i}`]}</li>)}
          </ul>
          <div className="actions">
            <Link className="button button--primary" href="/#roles">See open roles <ArrowRight size={18} aria-hidden="true" /></Link>
            <Link className="button button--outline" href="/#process">How we hire</Link>
          </div>
        </div>
      </section>

      <section className="section" id="roles" tabIndex={-1}>
        <div className="container">
          <SectionHead eyebrow={t.roles_eyebrow} title="Open roles" intro="Every role is remote and part-time, at least 15 hours a week. Choose one role that matches your strongest skills and evidence." />
          {roles === null ? (
            <p className="form-status" data-state="error" role="alert">
              We could not load the open roles just now. Please refresh in a minute, or email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.
            </p>
          ) : roles.length === 0 ? (
            <p>There are no open roles right now. New intakes are announced here and on LinkedIn; you are welcome to email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.</p>
          ) : (
            <ul className="role-cards">
              {roles.map((role) => (
                <li key={role.slug} className="role-card">
                  <span className="role-code" aria-hidden="true">{role.code}</span>
                  <h3><Link href={`/roles/${role.slug}`}>{role.title}</Link></h3>
                  <p className="role-meta">{role.family} · Remote · Part-time</p>
                  <p>{role.summary}</p>
                  <span className="text-link" aria-hidden="true">View role and apply <ArrowRight size={16} /></span>
                </li>
              ))}
            </ul>
          )}
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

      <section className="section" id="process" tabIndex={-1}>
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

      <section className="section section--tint" id="faq" tabIndex={-1}>
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
              <Sparkles size={24} strokeWidth={1.75} />
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
                <Mail size={16} aria-hidden="true" /> {t.cta_contact} <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>
              </p>
            </div>
            <Link className="button button--light" href="/#roles">See open roles <ArrowRight size={18} aria-hidden="true" /></Link>
          </div>
        </div>
      </section>
    </>
  );
}
