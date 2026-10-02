import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

export default defineConfig([
  ...nextVitals,
  ...nextTs,
  // app.js is the CommonJS startup file for `npm start`.
  globalIgnores([".next/**", "out/**", "build/**", "next-env.d.ts", "app.js"]),
]);
