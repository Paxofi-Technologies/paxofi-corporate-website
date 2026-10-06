"use client";

import { FileText } from "lucide-react";
import { FormEvent, useEffect, useRef, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { RESOURCE_CATEGORIES } from "@/lib/resources";
import {
  AdminApiError,
  MEDIA_ACCEPT,
  MediaCapabilities,
  MediaItem,
  formatBytes,
  formatDateTime,
  isImageFile,
  mediaHref,
  uploadMedia,
} from "@/lib/admin-api";

type Filter = "all" | "image" | "document";
const FILTERS: { value: Filter; label: string }[] = [
  { value: "all", label: "All" },
  { value: "image", label: "Images" },
  { value: "document", label: "Documents" },
];

/** Pictures and documents for the website (D-012). */
export default function MediaLibrary() {
  const { user, request, apiBase } = useAdmin();
  const [items, setItems] = useState<MediaItem[] | null>(null);
  const [limits, setLimits] = useState<MediaCapabilities | null>(null);
  const [filter, setFilter] = useState<Filter>("all");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  useEffect(() => {
    if (!can(user, "content.edit")) return;
    let cancelled = false;
    request<MediaItem[]>("/media")
      .then((result) => {
        if (cancelled) return;
        setItems(result.data);
        setLimits(result.meta as unknown as MediaCapabilities);
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The media library could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request]);

  if (!can(user, "content.edit")) return <NoAccess title="Media" />;

  const shown = (items ?? []).filter((item) => filter === "all" || item.kind === filter);
  const replace = (next: MediaItem) => setItems((current) => (current ?? []).map((item) => (item.id === next.id ? next : item)));
  const serverLimit = limits?.server_max_bytes ?? null;

  return (
    <>
      <h1 className="admin-title">Media</h1>
      <p className="admin-intro">
        Pictures and documents for the website. Choose them for a product, service, industry or article under <strong>Content</strong>, or
        list a document on the public <strong>Resources</strong> page. Anyone with a file&apos;s link can open it, so upload only material
        meant for the public.
      </p>
      <FormStatus state="success" message={notice} />
      <FormStatus state="error" message={error} />

      {limits && !limits.uploads && (
        <p className="form-status" data-state="error" role="status">
          File uploads are not set up on the server yet. An administrator needs to complete deployment guide Step 10.
        </p>
      )}
      {limits?.uploads && !limits.images && (
        <p className="form-status" data-state="error" role="status">The server cannot process pictures yet (PHP &quot;gd&quot;). Documents can be uploaded.</p>
      )}
      {limits?.uploads && serverLimit !== null && serverLimit < limits.document_max_bytes && (
        <p className="form-note">
          The server currently accepts files up to {formatBytes(serverLimit)}. Larger documents need the server limit raised (deployment
          guide Step 10).
        </p>
      )}

      {limits?.uploads !== false && (
        <Upload
          onUploaded={(item) => {
            setItems((current) => [item, ...(current ?? [])]);
            setNotice(`${item.filename} uploaded.`);
            setError("");
          }}
          apiBase={apiBase}
        />
      )}

      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Show">
          {FILTERS.map((option) => (
            <button key={option.value} type="button" className="admin-tab" aria-pressed={filter === option.value} onClick={() => setFilter(option.value)}>
              {option.label}
            </button>
          ))}
        </div>
      </div>

      {items === null && !error && <p role="status">Loading…</p>}
      {items !== null && shown.length === 0 && <p className="admin-empty">No files here yet.</p>}
      {shown.length > 0 && (
        <ul className="media-grid" aria-label="Files">
          {shown.map((item) => (
            <MediaCard
              key={item.id}
              item={item}
              href={mediaHref(apiBase, item.path)}
              canDelete={can(user, "content.publish")}
              onSaved={(next) => {
                replace(next);
                setNotice("Saved.");
              }}
              onDeleted={() => {
                setItems((current) => (current ?? []).filter((other) => other.id !== item.id));
                setNotice(`${item.filename} deleted.`);
              }}
            />
          ))}
        </ul>
      )}
    </>
  );
}

function Upload({ apiBase, onUploaded }: { apiBase?: string; onUploaded: (item: MediaItem) => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [detail, setDetail] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const input = useRef<HTMLInputElement>(null);
  const image = file !== null && isImageFile(file.name, file.type);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!file) {
      setFields({ file: "Choose a file to upload." });
      return;
    }
    if (image && detail.trim().length < 2) {
      setFields({ alt_text: "Describe the picture in a few words." });
      return;
    }
    setBusy(true);
    setFields({});
    setError("");
    try {
      const item = await uploadMedia(apiBase, file, image ? { alt_text: detail } : { title: detail });
      onUploaded(item);
      setFile(null);
      setDetail("");
      if (input.current) input.current.value = "";
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.fields.file || e.fields.alt_text || e.fields.title ? "" : e.message);
      } else {
        setError("The upload did not work. Please try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form-card media-upload" onSubmit={submit} noValidate aria-labelledby="upload-heading">
      <h2 id="upload-heading">Upload a file</h2>
      <FieldShell
        label="File"
        hint="Images: JPEG, PNG or WebP up to 5 MB. Documents: PDF, Word, Excel, PowerPoint, text or CSV up to 10 MB."
        error={fields.file}
      >
        {(props) => (
          <input
            ref={input}
            type="file"
            name="file"
            accept={MEDIA_ACCEPT}
            onChange={(event) => {
              setFile(event.target.files?.[0] ?? null);
              setFields({});
            }}
            {...props}
          />
        )}
      </FieldShell>
      {file && (
        <FieldShell
          label={image ? "Description of the picture" : "Title (optional)"}
          hint={image ? "Read aloud by screen readers, e.g. “Paxofi Pay on a phone”." : "Shown as the download link. Leave empty to use the file name."}
          error={image ? fields.alt_text : fields.title}
        >
          {(props) => <input type="text" name={image ? "alt_text" : "title"} value={detail} onChange={(e) => setDetail(e.target.value)} maxLength={image ? 200 : 120} {...props} />}
        </FieldShell>
      )}
      <FormStatus state="error" message={error} />
      <div className="actions">
        <button className="button button--primary" type="submit" disabled={busy}>
          {busy ? "Uploading…" : "Upload"}
        </button>
      </div>
    </form>
  );
}

function MediaCard({
  item,
  href,
  canDelete,
  onSaved,
  onDeleted,
}: {
  item: MediaItem;
  href: string;
  canDelete: boolean;
  onSaved: (item: MediaItem) => void;
  onDeleted: () => void;
}) {
  const { request } = useAdmin();
  const image = item.kind === "image";
  const [value, setValue] = useState((image ? item.alt_text : item.title) ?? "");
  const [fieldError, setFieldError] = useState("");
  const [error, setError] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [copied, setCopied] = useState(false);
  const [busy, setBusy] = useState(false);
  const label = image ? "Description" : "Title";

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setFieldError("");
    setError("");
    try {
      const result = await request<MediaItem>(`/media/${item.id}`, { method: "PATCH", body: image ? { alt_text: value } : { title: value } });
      onSaved(result.data);
    } catch (e) {
      if (e instanceof AdminApiError) setFieldError(e.fields.alt_text ?? e.fields.title ?? e.message);
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    setBusy(true);
    setError("");
    try {
      await request(`/media/${item.id}`, { method: "DELETE" });
      onDeleted();
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The file could not be deleted.");
      setConfirming(false);
    } finally {
      setBusy(false);
    }
  }

  async function copy() {
    try {
      await navigator.clipboard.writeText(href);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  }

  const facts = [
    `${item.format}, ${formatBytes(item.size_bytes)}`,
    image && item.width && item.height ? `${item.width} × ${item.height}` : "",
    `uploaded ${formatDateTime(item.created_at)}${item.uploaded_by ? ` by ${item.uploaded_by}` : ""}`,
  ].filter(Boolean);

  return (
    <li className="media-card">
      {image ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img className="media-thumb" src={href} alt="" width={item.width ?? undefined} height={item.height ?? undefined} loading="lazy" />
      ) : (
        <span className="media-doc" aria-hidden="true">
          <FileText size={36} strokeWidth={1.5} />
          {item.format}
        </span>
      )}
      <h2>{item.filename}</h2>
      <span className="admin-sub">{facts.join(" · ")}</span>
      <span className="admin-sub">{item.used_by.length > 0 ? `Used by ${item.used_by.join(", ")}` : "Not used on the website yet"}</span>
      <form onSubmit={save} noValidate>
        <FieldShell label={label} error={fieldError}>
          {(props) => <input type="text" value={value} onChange={(e) => setValue(e.target.value)} maxLength={image ? 200 : 120} {...props} />}
        </FieldShell>
        <div className="actions">
          <button className="button button--outline" type="submit" disabled={busy}>
            Save {label.toLowerCase()}
          </button>
          <a className="button button--outline" href={href} target="_blank" rel="noopener noreferrer">
            Open<span className="visually-hidden"> {item.filename}</span>
          </a>
          <button className="button button--outline" type="button" onClick={copy}>
            {copied ? "Link copied" : "Copy link"}
          </button>
        </div>
      </form>
      {canDelete &&
        (confirming ? (
          <div className="actions" role="group" aria-label={`Delete ${item.filename}?`}>
            <button className="button button--primary" type="button" onClick={remove} disabled={busy}>
              Yes, delete
            </button>
            <button className="button button--outline" type="button" onClick={() => setConfirming(false)}>
              Cancel
            </button>
          </div>
        ) : (
          <div className="actions">
            <button className="button button--outline" type="button" onClick={() => setConfirming(true)} disabled={item.used_by.length > 0}>
              Delete<span className="visually-hidden"> {item.filename}</span>
            </button>
          </div>
        ))}
      {canDelete && item.used_by.length > 0 && <span className="form-note">Remove it from the item and publish before deleting it.</span>}
      <FormStatus state="error" message={error} />
      {!image && <ResourceListing item={item} canPublish={canDelete} onSaved={onSaved} />}
    </li>
  );
}

/** A document's place on the public Resources page (D-023): administrators list it with a category and a short description. */
function ResourceListing({ item, canPublish, onSaved }: { item: MediaItem; canPublish: boolean; onSaved: (item: MediaItem) => void }) {
  const { request } = useAdmin();
  const listed = item.resource?.listed ?? false;
  const [category, setCategory] = useState(item.resource?.category ?? "");
  const [summary, setSummary] = useState(item.resource?.summary ?? "");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const status = listed
    ? `On the Resources page under ${RESOURCE_CATEGORIES[category as keyof typeof RESOURCE_CATEGORIES] ?? "Other documents"}.`
    : "Not on the Resources page.";

  async function send(nextListed: boolean) {
    setBusy(true);
    setFields({});
    setError("");
    try {
      const result = await request<MediaItem>(`/media/${item.id}/resource`, { method: "POST", body: { listed: nextListed, category, summary } });
      onSaved(result.data);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(Object.keys(e.fields).length > 0 ? "" : e.message);
      } else {
        setError("It could not be saved.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (!canPublish) return <span className="admin-sub">{status} An administrator lists documents there.</span>;

  return (
    <details className="media-resource" open={listed || undefined}>
      <summary>Resources page: {listed ? "listed" : "not listed"}</summary>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          void send(true);
        }}
        noValidate
      >
        <FieldShell label="Category" error={fields.category}>
          {(props) => (
            <select value={category} onChange={(e) => setCategory(e.target.value)} {...props}>
              <option value="">Choose…</option>
              {Object.entries(RESOURCE_CATEGORIES).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          )}
        </FieldShell>
        <FieldShell label="Short description" hint={`${[...summary.trim()].length} of 300 characters. At least 10. Shown under the title.`} error={fields.summary}>
          {(props) => <textarea rows={3} value={summary} onChange={(e) => setSummary(e.target.value)} {...props} />}
        </FieldShell>
        <FormStatus state="error" message={error} />
        <div className="actions">
          <button className="button button--primary" type="submit" disabled={busy}>
            {listed ? "Save changes" : "List on the Resources page"}
          </button>
          {listed && (
            <button className="button button--outline" type="button" onClick={() => void send(false)} disabled={busy}>
              Take off the Resources page
            </button>
          )}
        </div>
      </form>
    </details>
  );
}
