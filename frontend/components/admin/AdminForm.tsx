"use client";

import { useEffect, useId, useRef } from "react";

type Described = { "aria-invalid"?: true; "aria-describedby"?: string; id: string };

/**
 * Label, control, hint and error. The hint and error sit outside the <label>
 * so they are the control's description, not part of its name.
 */
export function FieldShell({
  label,
  hint,
  error,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  children: (props: Described) => React.ReactNode;
}) {
  const id = useId();
  const described = [hint ? `${id}-hint` : "", error ? `${id}-error` : ""].filter(Boolean).join(" ");
  return (
    <div className="admin-field">
      <label htmlFor={id}>{label}</label>
      {children({ id, "aria-invalid": error ? true : undefined, "aria-describedby": described || undefined })}
      {hint && <span id={`${id}-hint`} className="form-note">{hint}</span>}
      {error && <span id={`${id}-error`} className="field-error">{error}</span>}
    </div>
  );
}

type FieldProps = { name: string; label: string; error?: string; hint?: string } & React.InputHTMLAttributes<HTMLInputElement>;

export function Field({ name, label, error, hint, ...input }: FieldProps) {
  return (
    <FieldShell label={label} hint={hint} error={error}>
      {(props) => <input name={name} {...props} {...input} />}
    </FieldShell>
  );
}

/** Status line that announces itself and takes focus when it changes. */
export function FormStatus({ state, message }: { state: "success" | "error" | "info"; message: string }) {
  const ref = useRef<HTMLParagraphElement>(null);
  useEffect(() => {
    if (message) ref.current?.focus();
  }, [message]);
  if (!message) return null;
  return (
    <p ref={ref} tabIndex={-1} className="form-status" data-state={state} role={state === "error" ? "alert" : "status"}>
      {message}
    </p>
  );
}

export const PASSWORD_HINT = "At least 12 characters. A short phrase of unrelated words works well.";

export function formValues(form: HTMLFormElement): Record<string, string> {
  const values: Record<string, string> = {};
  for (const [key, value] of new FormData(form).entries()) {
    if (typeof value === "string") values[key] = value;
  }
  return values;
}
