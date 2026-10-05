import type { Metadata } from "next";
import ArticleEditorView from "../ArticleEditorView";

export const metadata: Metadata = { title: "Article" };

export default async function ArticlePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ArticleEditorView id={id} />;
}
