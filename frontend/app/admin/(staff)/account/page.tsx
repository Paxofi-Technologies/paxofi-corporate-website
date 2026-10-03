import type { Metadata } from "next";
import MyAccount from "./MyAccount";

export const metadata: Metadata = { title: "My account" };

export default function AccountPage() {
  return <MyAccount />;
}
