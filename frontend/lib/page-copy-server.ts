import { cache } from "react";
import { resolveApiBase } from "./contact";
import { loadPageCopy, type PageKey } from "./page-copy";

/** One API call per page per request, shared by the page and its metadata. */
export const pageCopy = cache((page: PageKey) => loadPageCopy(page, resolveApiBase(process.env)));
