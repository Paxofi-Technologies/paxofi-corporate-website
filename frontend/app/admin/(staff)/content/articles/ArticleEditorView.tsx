"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import ArticleBody from "@/components/ArticleBody";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { useMediaList } from "@/components/admin/CatalogForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type ArticleAdminDetail, type ArticleContentValues, formatDateTime, mediaHref } from "@/lib/admin-api";
import { ARTICLE_CATEGORIES, readingMinutes } from "@/lib/articles";

type Values = { title: string; category: ArticleContentValues["category"]; summary: string; body: string; author_name: string; image_id: string };
const EMPTY: Values = { title: "", category: "insight", summary: "", body: "", author_name: "", image_id: "" };
const toValues = (c: ArticleContentValues): Values => ({ ...c, author_name: c.author_name ?? "", image_id: c.image_id ?? "" });
// No maxLength on the inputs: the browser would silently cut pasted text. The count says when it is too long and the API refuses it.
const lengthNote = (text: string, max: number) => {
  const length = [...text.trim()].length;
  return length > max ? `${length} of ${max} characters: ${length - max} too many.` : `${length} of ${max} characters.`;
};

/** Write, preview, save and publish one News & Insights article (D-021). */
export default function ArticleEditorView({ id }: { id: string | null }) {
  const { apiBase, user, request } = useAdmin();
  const router = useRouter();
  const media = useMediaList();
  const [article, setArticle] = useState<ArticleAdminDetail | null>(null);
  const [values, setValues] = useState<Values>(EMPTY);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [busy, setBusy] = useState("");
  const [preview, setPreview] = useState(false);

  useEffect(() => {
    if (!id || !can(user, "content.edit")) return;
    let cancelled = false;
    request<ArticleAdminDetail>(`/articles/${encodeURIComponent(id)}`)
      .then((result) => {
        if (cancelled) return;
        setArticle(result.data);
        const content = result.data.draft?.content ?? result.data.live;
        if (content) setValues(toValues(content));
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The article could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [id, user, request]);

  if (!can(user, "content.edit")) return <NoAccess title="Article" />;
  const publisher = can(user, "content.publish");
  const set = (key: keyof Values) => (event: { target: { value: string } }) => setValues({ ...values, [key]: event.target.value });
  const images = (media ?? []).filter((m) => m.kind === "image");
  const image = images.find((m) => m.id === values.image_id);

  async function run(label: string, action: () => Promise<ArticleAdminDetail | null>, message: string) {
    setBusy(label);
    setError("");
    setSaved("");
    setFields({});
    try {
      const result = await action();
      if (result) setArticle(result);
      setSaved(message);
      return result;
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "That could not be saved.");
      if (e instanceof AdminApiError) setFields(e.fields);
      return null;
    } finally {
      setBusy("");
    }
  }

  const payload = () => ({ ...values, author_name: values.author_name || null, image_id: values.image_id || null });

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!id) {
      const created = await run("save", async () => (await request<ArticleAdminDetail>("/articles", { method: "POST", body: payload() })).data, "Draft saved.");
      if (created) router.replace(`/admin/content/articles/${created.id}`);
      return;
    }
    await run("save", async () => (await request<ArticleAdminDetail>(`/articles/${id}/draft`, { method: "POST", body: payload() })).data, publisher ? "Draft saved. Publish it to put it on the website." : "Draft saved. An administrator will publish it.");
  }

  const publish = () => run("publish", async () => (await request<ArticleAdminDetail>(`/articles/${id}/publish`, { method: "POST" })).data, "Published. The article is on the website.");
  const visibility = (visible: boolean) => run("visibility", async () => (await request<ArticleAdminDetail>(`/articles/${id}/visibility`, { method: "POST", body: { visible } })).data, visible ? "The article is on the website again." : "The article is hidden from the website.");
  async function discard() {
    const result = await run("discard", async () => (await request<ArticleAdminDetail>(`/articles/${id}/draft`, { method: "DELETE" })).data, "Draft discarded.");
    if (result?.live) setValues(toValues(result.live));
  }
  async function remove() {
    if (!window.confirm("Delete this article permanently? This cannot be undone.")) return;
    setBusy("delete");
    setError("");
    try {
      await request(`/articles/${id}`, { method: "DELETE" });
      router.replace("/admin/content?tab=articles");
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The article could not be deleted.");
      setBusy("");
    }
  }

  if (id && !article) {
    return (
      <>
        <p><Link href="/admin/content?tab=articles">← Articles</Link></p>
        <h1 className="admin-title">Article</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading…</p>}
      </>
    );
  }

  const state = article?.state;
  const unsaved = article ? JSON.stringify(payload()) !== JSON.stringify(article.draft?.content ?? article.live) : true;

  return (
    <>
      <p><Link href="/admin/content?tab=articles">← Articles</Link></p>
      <h1 className="admin-title">{id ? values.title || "Article" : "Write an article"}</h1>
      {article && (
        <p className="admin-intro">
          {state === "published" ? "On the website" : state === "hidden" ? "Hidden from the website" : "Not published yet"}
          {article.published_at && <> · first published {formatDateTime(article.published_at)}</>}
          {article.draft && <> · draft saved {formatDateTime(article.draft.saved_at)}{article.draft.author_name ? ` by ${article.draft.author_name}` : ""}</>}
          {state === "published" && <> · <a href={article.path} target="_blank" rel="noopener">View on the website</a></>}
        </p>
      )}
      <FormStatus state="success" message={saved} />
      <FormStatus state="error" message={error} />

      <div className="admin-tabs" role="group" aria-label="View">
        <button type="button" className="admin-tab" aria-pressed={!preview} onClick={() => setPreview(false)}>Write</button>
        <button type="button" className="admin-tab" aria-pressed={preview} onClick={() => setPreview(true)}>Preview</button>
      </div>

      {preview ? (
        <section className="form-card admin-panel-gap article-preview" aria-label="Preview">
          <p className="article-meta"><span className="article-category">{ARTICLE_CATEGORIES.find((c) => c.value === values.category)?.label}</span><span>{readingMinutes(values.body)} min read</span></p>
          <h2 className="article-preview-title">{values.title || "Title"}</h2>
          <p className="lead">{values.summary}</p>
          {image?.width && image.height ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img className="article-image" src={mediaHref(apiBase, image.path)} alt={image.alt_text ?? ""} width={image.width} height={image.height} />
          ) : null}
          <ArticleBody body={values.body} />
        </section>
      ) : (
        <form className="contact-form form-card admin-panel-gap" onSubmit={save} noValidate>
          <FieldShell label="Title" hint={`${lengthNote(values.title, 160)} At least 5. The web address is made from the first title and never changes.`} error={fields.title}>
            {(props) => <input name="title" value={values.title} onChange={set("title")} required {...props} />}
          </FieldShell>
          <div className="admin-row3">
            <FieldShell label="Category" error={fields.category}>
              {(props) => (
                <select name="category" value={values.category} onChange={set("category")} {...props}>
                  {ARTICLE_CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
              )}
            </FieldShell>
            <FieldShell label="Author (optional)" hint={`Shown as “By …”. ${lengthNote(values.author_name, 80)}`} error={fields.author_name}>
              {(props) => <input name="author_name" value={values.author_name} onChange={set("author_name")} {...props} />}
            </FieldShell>
          </div>
          <FieldShell label="Summary" hint={`${lengthNote(values.summary, 300)} Shown on the list, in search results and when the link is shared.`} error={fields.summary}>
            {(props) => <textarea name="summary" rows={3} value={values.summary} onChange={set("summary")} required {...props} />}
          </FieldShell>
          <FieldShell label="Picture (optional)" hint={images.length === 0 && media !== null ? "No pictures yet: upload one under Media." : "Shown at the top of the article and when it is shared. Wide pictures (1200 × 630) work best."} error={fields.image_id}>
            {(props) => (
              <select name="image_id" value={values.image_id} onChange={set("image_id")} {...props}>
                <option value="">No picture</option>
                {values.image_id && !image && <option value={values.image_id}>Chosen picture (loading…)</option>}
                {images.map((m) => <option key={m.id} value={m.id}>{m.alt_text ? `${m.alt_text} (${m.filename})` : m.filename}</option>)}
              </select>
            )}
          </FieldShell>
          <FieldShell
            label="Article"
            hint="Leave a blank line between paragraphs. ## starts a heading, - a bullet, 1. a numbered item. **bold**, [link text](https://…). No HTML."
            error={fields.body}
          >
            {(props) => <textarea name="body" rows={18} value={values.body} onChange={set("body")} required {...props} />}
          </FieldShell>
          <div className="admin-actions">
            <button className="button button--primary" type="submit" disabled={busy !== ""}>{busy === "save" ? "Saving…" : "Save draft"}</button>
          </div>
        </form>
      )}

      {article && (
        <section className="form-card admin-panel-gap" aria-labelledby="publish-heading">
          <h2 id="publish-heading">Publishing</h2>
          {!publisher ? (
            <p>Save your draft and ask an administrator to publish it.</p>
          ) : (
            <>
              {unsaved && <p className="admin-help">You have changes that are not saved. Save the draft first; publishing uses the saved draft.</p>}
              <div className="admin-actions">
                {article.draft && <button type="button" className="button button--primary" disabled={busy !== ""} onClick={publish}>{busy === "publish" ? "Publishing…" : state === "draft" ? "Publish" : "Publish changes"}</button>}
                {state === "published" && <button type="button" className="button button--outline" disabled={busy !== ""} onClick={() => visibility(false)}>Hide from the website</button>}
                {state === "hidden" && <button type="button" className="button button--outline" disabled={busy !== ""} onClick={() => visibility(true)}>Show on the website</button>}
                <button type="button" className="button button--outline admin-danger" disabled={busy !== ""} onClick={remove}>Delete article</button>
              </div>
            </>
          )}
          {article.draft && article.live && (
            <p><button type="button" className="admin-link-button" disabled={busy !== ""} onClick={discard}>Discard draft changes</button></p>
          )}
        </section>
      )}
    </>
  );
}
