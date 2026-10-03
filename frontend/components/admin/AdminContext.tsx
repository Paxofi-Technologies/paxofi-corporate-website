"use client";

import { usePathname, useRouter } from "next/navigation";
import { createContext, useCallback, useContext, useMemo, useState } from "react";
import { AdminApiError, Envelope, StaffUser, adminRequest } from "@/lib/admin-api";

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
        if (error instanceof AdminApiError && error.status === 401 && path !== "/session") {
          setUser(null);
          router.replace(`/admin/login?next=${encodeURIComponent(pathname)}&expired=1`);
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
