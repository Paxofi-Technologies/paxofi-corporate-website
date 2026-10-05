import type { Metadata } from "next";
import ArticleEditorView from "../ArticleEditorView";

export const metadata: Metadata = { title: "Write an article" };

export default function NewArticlePage() {
  return <ArticleEditorView id={null} />;
}
