import type { Locale } from './i18n';

/**
 * Localised URL segments.
 *
 * Routes are translated, not just prefixed: /fr/tournois/ is /en/tournaments/.
 * This table is the single place that knows the mapping — pages build links with
 * `route()` instead of interpolating a locale into a hard-coded path, and the
 * language switcher uses it to translate the current URL.
 *
 * Keys are stable internal names; they never appear in a URL.
 */
const SEGMENTS = {
  rules: { fr: 'regles', en: 'rules' },
  banlist: { fr: 'banlist', en: 'banlist' },
  validator: { fr: 'validateur', en: 'validator' },
  meta: { fr: 'meta', en: 'meta' },
  privacy: { fr: 'confidentialite', en: 'privacy' },
  decklists: { fr: 'decklist', en: 'decklist' },
  tournaments: { fr: 'tournois', en: 'tournaments' },
  // Single-segment slugs on purpose: two-level paths would break the language
  // switcher, which only translates the first segment after the locale.
  submitDecklist: { fr: 'soumettre-decklist', en: 'submit-decklist' },
} as const satisfies Record<string, Record<Locale, string>>;

export type RouteName = keyof typeof SEGMENTS;

/**
 * Build an absolute, localised path.
 *
 * `route('tournaments', 'fr')`            -> /fr/tournois/
 * `route('tournaments', 'en', 'artefact-1')` -> /en/tournaments/artefact-1/
 */
export function route(name: RouteName, locale: Locale, slug?: string): string {
  const segment = SEGMENTS[name][locale];
  return slug ? `/${locale}/${segment}/${slug}/` : `/${locale}/${segment}/`;
}

/** Home page for a locale. */
export function homeRoute(locale: Locale): string {
  return `/${locale}/`;
}

/**
 * Translate a pathname into the other locale.
 *
 * Swapping only the locale prefix is not enough: /fr/tournois/x/ would become
 * /en/tournois/x/, which does not exist. The first segment after the prefix is
 * looked up in the table and translated too; anything below it (a slug) is
 * carried over untouched.
 *
 * Unknown paths fall back to the target locale's home page rather than a 404.
 */
export function translatePath(pathname: string, target: Locale): string {
  const parts = pathname.split('/').filter(Boolean);

  // ['fr'] or [] -> home
  if (parts.length <= 1) return homeRoute(target);

  const [, section, ...rest] = parts;

  const entry = Object.entries(SEGMENTS).find(
    ([, translations]) => translations.fr === section || translations.en === section
  );
  if (!entry) return homeRoute(target);

  const translated = entry[1][target];
  const tail = rest.length ? `${rest.join('/')}/` : '';
  return `/${target}/${translated}/${tail}`;
}
