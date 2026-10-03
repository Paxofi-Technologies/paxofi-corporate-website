import type { Metadata } from "next";
import StaffUsers from "./StaffUsers";

export const metadata: Metadata = { title: "Users" };

export default function UsersPage() {
  return <StaffUsers />;
}
