import { FileDown } from "lucide-react";
import { CtaBand, PageHero } from "@/components/Sections";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { groupResources, loadResources } from "@/lib/resources";
import { SITE, pageMetadata } from "@/lib/site";

export const dynamic = "force-dynamic";

export async function generateMetadata() {
  const t = await pageCopy("resources");
  return pageMetadata("Resources", t.meta_description, "/resources");
}

/** Documents an administrator listed from the media library, grouped by category (D-023). */
export default async function Resources() {
  const [resources, t] = await Promise.all([loadResources(resolveApiBase(process.env)), pageCopy("resources")]);
  const groups = resources ? groupResources(resources) : [];

  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />

      <section className="section">
        <div className="container resource-groups">
          {resources === null ? (
            <p className="form-status" data-state="error" role="alert">
              We could not load the documents just now. Please refresh in a minute, or email <a href={`mailto:${SITE.email}`}>{SITE.email}</a>.
            </p>
          ) : groups.length === 0 ? (
            <p>{t.empty_text}</p>
          ) : (
            groups.map((group) => (
              <section key={group.category} aria-labelledby={`resources-${group.category}`}>
                <h2 id={`resources-${group.category}`} className="section-title">{group.label}</h2>
                <ul className="resource-list">
                  {group.items.map((item) => (
                    <li key={item.href} className="resource-item">
                      <span className="icon-badge icon-badge--blue" aria-hidden="true">
                        <FileDown size={22} strokeWidth={1.75} />
                      </span>
                      <div className="resource-item__text">
                        <h3>{item.title}</h3>
                        {item.summary && <p>{item.summary}</p>}
                      </div>
                      <a className="button button--outline resource-item__download" href={item.href} download>
                        Download<span className="visually-hidden"> {item.title}</span> <span className="resource-item__meta">({item.meta})</span>
                      </a>
                    </li>
                  ))}
                </ul>
              </section>
            ))
          )}
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
