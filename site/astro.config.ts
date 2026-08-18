import { defineConfig } from 'astro/config';
import sitemap, { ChangeFreqEnum } from '@astrojs/sitemap';
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
          item.changefreq = ChangeFreqEnum.WEEKLY;
          item.priority = 1.0;
        } else if (/\/banlist\/$/.test(item.url)) {
          item.changefreq = ChangeFreqEnum.MONTHLY;
          item.priority = 0.9;
        } else if (/\/(decklist|tournois|tournaments)\/[^/]+\/$/.test(item.url)) {
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
    // Two majors of Vite are installed: Astro resolves its own pinned 6.x, while
    // @tailwindcss/vite pulls 8.x, whose Plugin type differs in an internal
    // `hotUpdate` signature. The plugin is fine at runtime — only the nominal
    // types clash. This was the long-standing "1 pre-existing error"; deduping
    // Vite would mean overriding a build dependency to silence a type.
    plugins: [tailwindcss() as any],
  },
});
