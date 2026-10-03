import type { Metadata } from "next";
import LoginForm from "./LoginForm";
import { safeNextPath } from "@/lib/admin-api";

export const metadata: Metadata = { title: "Sign in" };

type Query = Promise<Record<string, string | string[] | undefined>>;

export default async function LoginPage({ searchParams }: { searchParams: Query }) {
  const query = await searchParams;
  const notice = query.expired ? "Your session has ended. Please sign in again." : query.signed_out ? "You have signed out." : "";
  // mfa=1: the password was accepted earlier; only the authenticator code is missing (D-010).
  return <LoginForm notice={notice} next={safeNextPath(typeof query.next === "string" ? query.next : null)} codeStep={query.mfa === "1"} />;
}
