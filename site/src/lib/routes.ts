import type { Locale } from './i18n';

/**
 * Localised URL segments.
 *
 * Routes are translated, not just prefixed: /fr/tournois/ is /en/tournaments/ is
 * /it/tornei/. This table is the single place that knows the mapping — pages
 * build links with `route()` instead of interpolating a locale into a hard-coded
 * path, and the language switcher uses it to translate the current URL.
 *
 * Keys are stable internal names; they never appear in a URL.
 *
 * Italian segments are unaccented on purpose (`comunita`, not `comunità`): an
 * accented path has to be percent-encoded, which is ugly in a shared link and
 * easy to mistype.
 */
const SEGMENTS = {
  rules: { fr: 'regles', en: 'rules', it: 'regole' },
  banlist: { fr: 'banlist', en: 'banlist', it: 'banlist' },
  validator: { fr: 'validateur', en: 'validator', it: 'validatore' },
  meta: { fr: 'meta', en: 'meta', it: 'meta' },
  privacy: { fr: 'confidentialite', en: 'privacy', it: 'privacy' },
  decklists: { fr: 'decklist', en: 'decklist', it: 'decklist' },
  tournaments: { fr: 'tournois', en: 'tournaments', it: 'tornei' },
  community: { fr: 'communaute', en: 'community', it: 'comunita' },
  // Single-segment slugs on purpose: two-level paths would break the language
  // switcher, which only translates the first segment after the locale.
  submitDecklist: { fr: 'soumettre-decklist', en: 'submit-decklist', it: 'invia-decklist' },
  submitTournament: { fr: 'soumettre-tournoi', en: 'submit-tournament', it: 'invia-torneo' },
} as const satisfies Record<string, Record<Locale, string>>;

export type RouteName = keyof typeof SEGMENTS;

/**
 * Build an absolute, localised path.
 *
 * `route('tournaments', 'fr')`               -> /fr/tournois/
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
 * Translate a pathname into another locale.
 *
 * Swapping only the locale prefix is not enough: /fr/tournois/x/ would become
 * /it/tournois/x/, which does not exist. The first segment after the prefix is
 * looked up in the table and translated too; anything below it (a slug) is
 * carried over untouched.
 *
 * The segment is matched against every locale's spelling, not just FR and EN —
 * otherwise translating away from an Italian URL would fall through to the home
 * page.
 *
 * Unknown paths fall back to the target locale's home page rather than a 404.
 */
export function translatePath(pathname: string, target: Locale): string {
  const parts = pathname.split('/').filter(Boolean);

  // ['fr'] or [] -> home
  if (parts.length <= 1) return homeRoute(target);

  const [, section, ...rest] = parts;

  const entry = Object.entries(SEGMENTS).find(([, translations]) =>
    (Object.values(translations) as string[]).includes(section)
  );
  if (!entry) return homeRoute(target);

  const translated = entry[1][target];
  const tail = rest.length ? `${rest.join('/')}/` : '';
  return `/${target}/${translated}/${tail}`;
}
