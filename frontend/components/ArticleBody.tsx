import Link from "next/link";
import { type Block, type Inline, parseBody } from "@/lib/articles";

/** An article body as headings, paragraphs and lists. React escapes every word; no HTML is inserted. */
export default function ArticleBody({ body }: { body: string }) {
  return <div className="prose article-body">{parseBody(body).map((block, i) => <BlockView key={i} block={block} />)}</div>;
}

function BlockView({ block }: { block: Block }) {
  if (block.type === "heading") {
    return block.level === 2 ? <h2><InlineView parts={block.content} /></h2> : <h3><InlineView parts={block.content} /></h3>;
  }
  if (block.type === "list") {
    const items = block.items.map((item, i) => <li key={i}><InlineView parts={item} /></li>);
    return block.ordered ? <ol>{items}</ol> : <ul>{items}</ul>;
  }
  return <p><InlineView parts={block.content} /></p>;
}

function InlineView({ parts }: { parts: Inline[] }) {
  return (
    <>
      {parts.map((part, i) => {
        if (part.type === "bold") return <strong key={i}>{part.text}</strong>;
        if (part.type === "link") {
          return part.href.startsWith("/") ? <Link key={i} href={part.href}>{part.text}</Link> : <a key={i} href={part.href} rel="noopener noreferrer">{part.text}</a>;
        }
        return part.text;
      })}
    </>
  );
}
