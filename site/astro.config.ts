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
    }),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});
