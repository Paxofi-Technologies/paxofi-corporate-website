import type { Metadata } from "next";
import CareerRoles from "./CareerRoles";

export const metadata: Metadata = { title: "Careers roles" };

export default function CareerRolesPage() {
  return <CareerRoles />;
}
