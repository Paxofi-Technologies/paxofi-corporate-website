import type { Metadata } from "next";
import CatalogItemEditor from "./CatalogItemEditor";

export const metadata: Metadata = { title: "Edit content" };

export default async function EditItemPage({ params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return <CatalogItemEditor type={type} id={id} />;
}
