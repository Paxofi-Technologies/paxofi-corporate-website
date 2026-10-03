import type { Metadata } from "next";
import NewCatalogItem from "./NewCatalogItem";

export const metadata: Metadata = { title: "Add content" };

export default async function NewItemPage({ params }: { params: Promise<{ type: string }> }) {
  const { type } = await params;
  return <NewCatalogItem type={type} />;
}
