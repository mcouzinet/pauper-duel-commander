import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  // Canonical production URL, used for the sitemap and absolute links.
  site: 'https://pauperduelcommander.fr',
  output: 'static',
  trailingSlash: 'always',
  integrations: [
    sitemap({
      // Emit hreflang alternates so search engines pair the FR and EN routes.
      // The locale key must match the first path segment (/fr/…, /en/…).
      i18n: {
        defaultLocale: 'fr',
        locales: { fr: 'fr-FR', en: 'en-GB' },
      },
      // Everything is generated in one pass, so the build time is the honest
      // last-modified date for every page.
      lastmod: new Date(),
      serialize(item) {
        // The home page and the ban list change most often; detail pages least.
        if (/\/(fr|en)\/$/.test(item.url)) {
          item.changefreq = 'weekly';
          item.priority = 1.0;
        } else if (/\/banlist\/$/.test(item.url)) {
          item.changefreq = 'monthly';
          item.priority = 0.9;
        } else if (/\/(decklist|tournois|tournaments)\/[^/]+\/$/.test(item.url)) {
          item.changefreq = 'yearly';
          item.priority = 0.6;
        } else {
          item.changefreq = 'monthly';
          item.priority = 0.8;
        }
        return item;
      },
    }),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});
