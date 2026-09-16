export const siteRoutes = [
  { path: '/', label: 'Home', public: true },
  { path: '/services', label: 'Services', public: true },
  { path: '/products', label: 'Products', public: true },
  { path: '/careers', label: 'Careers', public: true },
  { path: '/contact', label: 'Contact', public: true },
] as const;

export type SiteRoute = (typeof siteRoutes)[number];
