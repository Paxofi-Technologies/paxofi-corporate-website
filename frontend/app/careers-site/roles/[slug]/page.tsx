import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import ApplicationForm from "./ApplicationForm";
import { RECRUITMENT_EMAIL, loadRole, loadStatus } from "@/lib/careers";
import { resolveApiBase } from "@/lib/contact";

export const dynamic = "force-dynamic";

type Props = { params: Promise<{ slug: string }>; searchParams?: Promise<{ test?: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const role = await loadRole(resolveApiBase(process.env), (await params).slug);
  if (role === null || role === "missing") return { title: "Role" };
  const path = `/roles/${role.slug}`;
  return { title: role.title, description: role.summary, alternates: { canonical: path }, openGraph: { title: `${role.title} | Paxofi Careers`, description: role.summary, url: path } };
}

/** One open role: what it involves and how it is assessed, then the application form (D-018, D-019). */
export default async function RolePage({ params, searchParams }: Props) {
  const apiBase = resolveApiBase(process.env);
  const [role, status] = await Promise.all([loadRole(apiBase, (await params).slug), loadStatus(apiBase)]);
  // Staff add ?test to this address to reveal the test-code field for controlled test applications (Gate 1).
  const testMode = typeof (await searchParams)?.test === "string";
  const canApply = status === null || status.open || testMode;
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
          <span className="eyebrow">{role.family} · PIF {role.engagement.label}</span>
          <h1>{role.title}</h1>
          <p className="lead">{role.purpose}</p>
          <ul className="role-facts" aria-label="At a glance">
            <li><strong>Where</strong> Remote</li>
            <li><strong>Time</strong> At least {role.engagement.hoursMin} hours a week ({role.engagement.hoursTarget} recommended)</li>
            <li><strong>Term</strong> {role.engagement.term}</li>
            <li><strong>Pay</strong> Unpaid: a learning and development programme, not employment</li>
            <li><strong>Who it is for</strong> {role.engagement.forWhom}</li>
          </ul>
          <a className="button button--primary" href="#apply">{canApply ? "Apply for this role" : "When applications open"}</a>
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
            <p className="form-note">Every applicant for this role is reviewed with the same scorecard. Any role task tests capability and is never work that Paxofi intends to use.</p>
          </aside>
        </div>
      </section>

      <section className="section section--tint" id="apply" tabIndex={-1}>
        <div className="container">
          <div className="form-card application-card">
            <h2>Apply for {role.title}</h2>
            {canApply ? (
              <>
                <p className="form-note">It takes about 10 minutes. You can apply to one role at a time. Fields marked “optional” can be left empty.</p>
                <ApplicationForm apiBase={apiBase} slug={role.slug} roleTitle={role.title} evidenceHint={role.evidence} minHours={role.engagement.hoursMin} targetHours={role.engagement.hoursTarget} engagementLabel={role.engagement.label} testMode={testMode} />
              </>
            ) : (
              <p className="notice" role="status">
                {status?.closed ? (
                  <><strong>Applications are closed{status.closesOn ? ` (the last day was ${status.closesOn})` : ""}.</strong> Thank you for your interest. New intakes are announced on this site. Questions? Email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.</>
                ) : (
                  <><strong>Applications open{status?.opensOn ? ` on ${status.opensOn}` : " soon"}.</strong> Read the role now and come back then to apply. Questions? Email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.</>
                )}
              </p>
            )}
          </div>
        </div>
      </section>
    </>
  );
}
