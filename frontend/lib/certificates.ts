/**
 * Checking a participation certificate (decision D-029). The public page sends
 * the code to the API and shows only what the API returns: the facts printed
 * on a valid certificate, or just that a certificate is no longer valid.
 */

const TIMEOUT_MS = 4000;
/** The characters a code uses: no 0, 1, I, L or O, which are easy to misread. */
const CODE_BODY = /^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{12}$/;

export type CertificateFacts = {
  code: string;
  holderName: string;
  roleTitle: string;
  engagementLabel: string;
  cohort: string | null;
  startedOn: string | null;
  completedOn: string;
  issuedOn: string;
  programme: string;
  message: string;
};

export type CertificateCheck =
  | { kind: "valid"; certificate: CertificateFacts }
  | { kind: "revoked"; code: string; message: string }
  | { kind: "not_found" }
  | { kind: "error" };

/** "pif k7m2 9qxa r4td" becomes "PIF-K7M2-9QXA-R4TD"; anything that cannot be a code is null. */
export function normaliseCertificateCode(input: string): string | null {
  const compact = input.replace(/[\s-]+/g, "").toUpperCase();
  if (!compact.startsWith("PIF") || !CODE_BODY.test(compact.slice(3))) return null;
  const body = compact.slice(3);
  return `PIF-${body.slice(0, 4)}-${body.slice(4, 8)}-${body.slice(8, 12)}`;
}

function apiRoot(apiBase: string | undefined): string {
  return (apiBase ?? "").trim().replace(/\/+$/, "") || "/api/v1";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

const text = (value: unknown): string => (typeof value === "string" ? value : "");
const textOrNull = (value: unknown): string | null => (typeof value === "string" && value !== "" ? value : null);

/** Asks the API about a code. A malformed code never leaves the page. */
export async function checkCertificate(apiBase: string | undefined, input: string, fetchImpl: typeof fetch = fetch): Promise<CertificateCheck> {
  const code = normaliseCertificateCode(input);
  if (code === null) return { kind: "not_found" };
  try {
    const response = await fetchImpl(`${apiRoot(apiBase)}/careers/certificates/${code}`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (response.status === 404) return { kind: "not_found" };
    if (!response.ok) return { kind: "error" };
    const payload: unknown = await response.json();
    const data = isRecord(payload) ? payload.data : null;
    if (!isRecord(data)) return { kind: "error" };
    if (data.status === "revoked") return { kind: "revoked", code: text(data.code), message: text(data.message) };
    if (data.status !== "valid" || !isRecord(data.engagement)) return { kind: "error" };
    return {
      kind: "valid",
      certificate: {
        code: text(data.code),
        holderName: text(data.holder_name),
        roleTitle: text(data.role_title),
        engagementLabel: text(data.engagement.label),
        cohort: textOrNull(data.cohort),
        startedOn: textOrNull(data.started_on),
        completedOn: text(data.completed_on),
        issuedOn: text(data.issued_on),
        programme: text(data.programme),
        message: text(data.message),
      },
    };
  } catch {
    return { kind: "error" };
  }
}

/** "2027-04-20" as "20 April 2027" (UTC, so the day never shifts). */
export function formatCertificateDate(iso: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
  if (!match) return iso;
  return new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))).toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" });
}
