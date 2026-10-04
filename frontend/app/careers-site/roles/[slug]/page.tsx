import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import ApplicationForm from "./ApplicationForm";
import { RECRUITMENT_EMAIL, loadRole } from "@/lib/careers";
import { resolveApiBase } from "@/lib/contact";

export const dynamic = "force-dynamic";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const role = await loadRole(resolveApiBase(process.env), (await params).slug);
  if (role === null || role === "missing") return { title: "Role" };
  const path = `/roles/${role.slug}`;
  return { title: role.title, description: role.summary, alternates: { canonical: path }, openGraph: { title: `${role.title} | Paxofi Careers`, description: role.summary, url: path } };
}

/** One open role: what it involves and how it is assessed, then the application form (D-018, D-019). */
export default async function RolePage({ params }: Props) {
  const apiBase = resolveApiBase(process.env);
  const role = await loadRole(apiBase, (await params).slug);
  if (role === "missing") notFound();
  if (role === null) {
    return (
      <section className="section">
        <div className="container prose">
          <h1>Role details unavailable</h1>
          <p role="alert">We could not load this role just now. Please refresh in a minute, or email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.</p>
          <p><Link href="/#roles">See all open roles</Link></p>
        </div>
      </section>
    );
  }

  const lists = [
    { title: "What you will do", items: role.responsibilities },
    { title: "What you will produce", items: role.deliverables },
    { title: "Skills we look for", items: role.competencies },
    { title: "Tools you may use", items: role.tools },
  ].filter((list) => list.items.length > 0);

  return (
    <>
      <section className="page-hero">
        <div className="container page-hero-inner">
          <p className="careers-back"><Link href="/#roles"><ArrowLeft size={16} aria-hidden="true" /> All open roles</Link></p>
          <span className="eyebrow">{role.family} · Paxofi Innovation Fellowship</span>
          <h1>{role.title}</h1>
          <p className="lead">{role.purpose}</p>
          <ul className="role-facts" aria-label="At a glance">
            <li><strong>Where</strong> Remote</li>
            <li><strong>Time</strong> At least 15 hours a week (20 recommended)</li>
            <li><strong>Term</strong> 3, 6 or 12 months, confirmed at offer</li>
            <li><strong>Pay</strong> Unpaid fellowship: no stipend or salary</li>
          </ul>
          <a className="button button--primary" href="#apply">Apply for this role</a>
        </div>
      </section>

      <section className="section">
        <div className="container role-detail">
          <div className="role-lists">
            {lists.map((list) => (
              <div key={list.title}>
                <h2>{list.title}</h2>
                <ul className="check-list check-list--light">
                  {list.items.map((item) => <li key={item}>{item}</li>)}
                </ul>
              </div>
            ))}
          </div>
          <aside className="feature-panel role-assessment" aria-labelledby="assessment-heading">
            <h2 id="assessment-heading">How we assess this role</h2>
            <dl>
              {role.evidence && (<><dt>What to show us</dt><dd>{role.evidence}</dd></>)}
              {role.assessment && (<><dt>Assessment</dt><dd>{role.assessment}</dd></>)}
              {role.interview && (<><dt>Interview focus</dt><dd>{role.interview}</dd></>)}
            </dl>
            <p className="form-note">Every applicant is reviewed with the same scorecard. Assessments test capability; they are never unpaid production work for Paxofi.</p>
          </aside>
        </div>
      </section>

      <section className="section section--tint" id="apply" tabIndex={-1}>
        <div className="container">
          <div className="form-card application-card">
            <h2>Apply for {role.title}</h2>
            <p className="form-note">It takes about 10 minutes. You can apply to one role at a time. Fields marked “optional” can be left empty.</p>
            <ApplicationForm apiBase={apiBase} slug={role.slug} roleTitle={role.title} evidenceHint={role.evidence} />
          </div>
        </div>
      </section>
    </>
  );
}
