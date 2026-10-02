import { useId } from "react";

type Props = {
  /** "light" for light backgrounds (Navy wordmark), "dark" for Navy backgrounds (white wordmark). */
  tone?: "light" | "dark";
  /** Show the PEOPLE | PRODUCTS | POSSIBILITIES descriptor under the wordmark. */
  descriptor?: boolean;
  className?: string;
};

/** The Paxofi "P" mark: Paxofi Blue → Sky Blue gradient (Brand Guidelines v1.0 §3). */
export function LogoMark({ size = 36, className }: { size?: number; className?: string }) {
  const id = useId().replace(/:/g, "");
  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 48 48"
      aria-hidden="true"
      focusable="false"
    >
      <defs>
        <linearGradient id={`pg-${id}`} x1="6" y1="44" x2="42" y2="4" gradientUnits="userSpaceOnUse">
          <stop offset="0" stopColor="#0047D6" />
          <stop offset="0.55" stopColor="#0066FF" />
          <stop offset="1" stopColor="#00B4FF" />
        </linearGradient>
      </defs>
      <path
        fill={`url(#pg-${id})`}
        fillRule="evenodd"
        d="M14 4h13a15 15 0 0 1 0 30h-6v6a6 6 0 0 1-12 0V9a5 5 0 0 1 5-5Zm7 10v10h6a5 5 0 0 0 0-10h-6Z"
      />
    </svg>
  );
}

/** Full logo: mark + "Paxofi" wordmark + "Technologies LTD". */
export default function Logo({ tone = "light", descriptor = false, className }: Props) {
  return (
    <span className={`logo logo--${tone}${className ? ` ${className}` : ""}`}>
      <LogoMark className="logo-mark" />
      <span className="logo-text">
        <span className="logo-word">Paxofi</span>
        <span className="logo-sub">Technologies LTD</span>
        {descriptor && <span className="logo-descriptor">People | Products | Possibilities</span>}
      </span>
    </span>
  );
}
