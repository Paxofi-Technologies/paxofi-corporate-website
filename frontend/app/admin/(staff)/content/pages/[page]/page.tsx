import type { Metadata } from "next";
import PageTextEditor from "./PageTextEditor";

export const metadata: Metadata = { title: "Edit page text" };

export default async function EditPageTextPage({ params }: { params: Promise<{ page: string }> }) {
  const { page } = await params;
  return <PageTextEditor page={page} />;
}
