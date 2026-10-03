"use client";

import { usePathname, useRouter } from "next/navigation";
import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { AdminApiError, Envelope, StaffUser, TWO_FACTOR_PATH, adminRequest } from "@/lib/admin-api";

type Request = <T>(path: string, init?: { method?: string; body?: unknown }) => Promise<Envelope<T>>;

type AdminContextValue = {
  apiBase?: string;
  /** The signed-in staff member; undefined until the session has been checked. */
  user: StaffUser | null | undefined;
  setUser: (user: StaffUser | null) => void;
  /** Calls the admin API; a 401 (session expired or revoked) sends the user to sign in. */
  request: Request;
};

const AdminContext = createContext<AdminContextValue | null>(null);

export function AdminProvider({ apiBase, children }: { apiBase?: string; children: React.ReactNode }) {
  const [user, setUser] = useState<StaffUser | null | undefined>(undefined);
  const router = useRouter();
  const pathname = usePathname();

  const request = useCallback<Request>(
    async (path, init) => {
      try {
        return await adminRequest(apiBase, path, init);
      } catch (error) {
        // Sign-in calls handle their own errors; anything else follows the session state.
        const signInCall = path === "/session" || path === "/session/mfa";
        if (error instanceof AdminApiError && !signInCall) {
          if (error.status === 401) {
            setUser(null);
            const reason = error.code === "MFA_REQUIRED" ? "mfa=1" : "expired=1";
            router.replace(`/admin/login?next=${encodeURIComponent(pathname)}&${reason}`);
          } else if (error.status === 403 && error.code === "MFA_ENROLLMENT_REQUIRED") {
            router.replace(TWO_FACTOR_PATH);
          }
        }
        throw error;
      }
    },
    [apiBase, pathname, router],
  );

  const value = useMemo(() => ({ apiBase, user, setUser, request }), [apiBase, user, request]);
  return <AdminContext.Provider value={value}>{children}</AdminContext.Provider>;
}

export function useAdmin(): AdminContextValue {
  const value = useContext(AdminContext);
  if (!value) throw new Error("useAdmin must be used inside AdminProvider");
  return value;
}

export function can(user: StaffUser | null | undefined, permission: string): boolean {
  return Boolean(user?.permissions.includes(permission));
}
