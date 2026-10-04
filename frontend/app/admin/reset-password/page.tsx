import type { Metadata } from "next";
import ResetPasswordForm from "./ResetPasswordForm";

export const metadata: Metadata = { title: "Choose a new password", referrer: "no-referrer" };

export default function ResetPasswordPage() {
  return <ResetPasswordForm />;
}
