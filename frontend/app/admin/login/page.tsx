import type { Metadata } from "next";
import LoginForm from "./LoginForm";
import { safeNextPath } from "@/lib/admin-api";

export const metadata: Metadata = { title: "Sign in" };

type Query = Promise<Record<string, string | string[] | undefined>>;

export default async function LoginPage({ searchParams }: { searchParams: Query }) {
  const query = await searchParams;
  const notice = query.expired ? "Your session has ended. Please sign in again." : query.signed_out ? "You have signed out." : "";
  return <LoginForm notice={notice} next={safeNextPath(typeof query.next === "string" ? query.next : null)} />;
}
