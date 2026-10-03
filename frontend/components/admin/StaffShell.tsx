"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import Logo from "@/components/Logo";
import { can, useAdmin } from "./AdminContext";
import { AdminApiError, StaffUser, TWO_FACTOR_PATH, adminRequest } from "@/lib/admin-api";

const NAV = [
  { href: "/admin/enquiries", label: "Enquiries", permission: "enquiries.read" },
  { href: "/admin/content", label: "Content", permission: "content.edit" },
  { href: "/admin/media", label: "Media", permission: "content.edit" },
  { href: "/admin/users", label: "Users", permission: "users.manage" },
  { href: "/admin/audit", label: "Audit log", permission: "audit.read" },
  { href: "/admin/account", label: "My account", permission: null },
];

/** Signed-in chrome: checks the session, then shows the navigation the role allows. */
export default function StaffShell({ children }: { children: React.ReactNode }) {
  const { apiBase, user, setUser } = useAdmin();
  const router = useRouter();
  const pathname = usePathname();
  const [failure, setFailure] = useState("");
  // Set while signing out, so the cleared user does not trigger a fresh session check.
  const leaving = useRef(false);

  useEffect(() => {
    if (user || leaving.current) return;
    let cancelled = false;
    adminRequest<StaffUser>(apiBase, "/session")
      .then((result) => !cancelled && setUser(result.data))
      .catch((error) => {
        if (cancelled) return;
        if (error instanceof AdminApiError && error.status === 401) {
          const step = error.code === "MFA_REQUIRED" ? "&mfa=1" : "";
          router.replace(`/admin/login?next=${encodeURIComponent(pathname)}${step}`);
        } else {
          setFailure(error instanceof AdminApiError ? error.message : "The staff area could not be loaded.");
        }
      });
    return () => {
      cancelled = true;
    };
  }, [apiBase, user, setUser, router, pathname]);

  // Administrators set up two-factor sign-in before anything else (D-010).
  const enrolling = Boolean(user?.two_factor_enrollment_required);
  useEffect(() => {
    if (enrolling && pathname !== TWO_FACTOR_PATH) router.replace(TWO_FACTOR_PATH);
  }, [enrolling, pathname, router]);

  async function signOut() {
    leaving.current = true;
    try {
      await adminRequest(apiBase, "/session", { method: "DELETE" });
    } catch {
      // The cookie may already be gone; signing out locally is still right.
    }
    setUser(null);
    router.replace("/admin/login?signed_out=1");
  }

  return (
    <>
      <header className="admin-bar">
        <div className="admin-bar__inner">
          <Link href="/admin/enquiries" className="admin-brand" aria-label="Staff area home">
            <Logo tone="dark" />
          </Link>
          <span className="admin-badge">Staff area</span>
          {user && (
            <>
              <nav aria-label="Staff area" className="admin-nav">
                <ul>
                  {NAV.filter((item) => (enrolling ? item.permission === null : item.permission === null || can(user, item.permission))).map((item) => (
                    <li key={item.href}>
                      <Link href={item.href} aria-current={pathname.startsWith(item.href) ? "page" : undefined}>
                        {item.label}
                      </Link>
                    </li>
                  ))}
                </ul>
              </nav>
              <div className="admin-user">
                <span>
                  {user.display_name}
                  <span className="admin-user__role">{user.role_label ?? "No role"}</span>
                </span>
                <button type="button" className="button button--light admin-signout" onClick={signOut}>
                  Sign out
                </button>
              </div>
            </>
          )}
        </div>
      </header>
      <main id="main" tabIndex={-1} className="admin-main">
        {user && (!enrolling || pathname === TWO_FACTOR_PATH) ? (
          children
        ) : user ? (
          <p role="status">Opening two-factor set-up…</p>
        ) : failure ? (
          <>
            <h1 className="admin-title">Staff area unavailable</h1>
            <p role="alert" className="form-status" data-state="error">{failure}</p>
          </>
        ) : (
          <>
            <h1 className="admin-title">Staff area</h1>
            <p role="status">Checking your session…</p>
          </>
        )}
      </main>
    </>
  );
}

/** Shown in place of a page the signed-in role may not use (the API refuses it too). */
export function NoAccess({ title }: { title: string }) {
  return (
    <>
      <h1 className="admin-title">{title}</h1>
      <p>Your role does not include access to this page. Ask an administrator if you need it.</p>
    </>
  );
}
