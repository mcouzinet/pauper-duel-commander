import { defineConfig } from 'astro/config';
import sitemap, { ChangeFreqEnum } from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  // Canonical production URL, used for the sitemap and absolute links.
  site: 'https://pauperduelcommander.fr',
  output: 'static',
  trailingSlash: 'always',
  // Astro 7 defaults to 'jsx', which drops the whitespace between inline elements
  // written on separate lines: nav labels and card text ran together.
  compressHTML: true,
  integrations: [
    sitemap({
      // Emit hreflang alternates so search engines pair the localised routes.
      // The locale key must match the first path segment (/fr/…, /en/…, /it/…).
      i18n: {
        defaultLocale: 'fr',
        locales: { fr: 'fr-FR', en: 'en-GB', it: 'it-IT' },
      },
      // Everything is generated in one pass, so the build time is the honest
      // last-modified date for every page.
      lastmod: new Date(),
      serialize(item) {
        // The home page and the ban list change most often; detail pages least.
        if (/\/(fr|en|it)\/$/.test(item.url)) {
          item.changefreq = ChangeFreqEnum.WEEKLY;
          item.priority = 1.0;
        } else if (/\/banlist\/$/.test(item.url)) {
          item.changefreq = ChangeFreqEnum.MONTHLY;
          item.priority = 0.9;
        } else if (/\/(decklist|tournois|tournaments|tornei)\/[^/]+\/$/.test(item.url)) {
          item.changefreq = ChangeFreqEnum.YEARLY;
          item.priority = 0.6;
        } else {
          item.changefreq = ChangeFreqEnum.MONTHLY;
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
