import fr from '../i18n/fr.json';
import en from '../i18n/en.json';
import it from '../i18n/it.json';
import { translatePath } from './routes';

export type Locale = 'fr' | 'en' | 'it';

/** Available locales. First one is the default. */
export const locales: Locale[] = ['fr', 'en', 'it'];

const messages: Record<Locale, Record<string, unknown>> = { fr, en, it };

/**
 * Lookup order for a missing string.
 *
 * English is the backup for every locale: a half-translated page reads better in
 * English than in French for an Italian speaker. French stays last because it is
 * the locale the content is authored in, so it is the only one guaranteed
 * complete.
 */
const FALLBACKS: Record<Locale, Locale[]> = {
  fr: ['fr', 'en'],
  en: ['en', 'fr'],
  it: ['it', 'en', 'fr'],
};

/**
 * How each language names itself.
 *
 * Autonyms on purpose: a French speaker looking for Italian scans for
 * "Italiano", not "Italien". The switcher is the one place on the site that is
 * never translated.
 */
export const LOCALE_LABELS: Record<Locale, { short: string; long: string }> = {
  fr: { short: 'FR', long: 'Français' },
  en: { short: 'EN', long: 'English' },
  it: { short: 'IT', long: 'Italiano' },
};

/** BCP 47 tag, for Intl and for the `inLanguage` metadata. */
const LOCALE_TAGS: Record<Locale, string> = {
  fr: 'fr-FR',
  en: 'en-GB',
  it: 'it-IT',
};

/**
 * Get a translated string by dot-notation key.
 *
 * Falls back through FALLBACKS, then returns the key itself so a missing string
 * is visible rather than blank.
 */
export function t(key: string, locale: Locale = 'fr'): string {
  for (const candidate of FALLBACKS[locale] ?? FALLBACKS.fr) {
    const value = getNestedValue(messages[candidate], key);
    if (value !== undefined) return String(value);
  }
  return key;
}

/**
 * The whole message tree for a locale, with the fallback chain already merged in.
 *
 * A few pages (rules, validator, submission) walk a whole branch of the tree
 * instead of asking for one key at a time. They used to pick the raw JSON with
 * `locale === 'en' ? en : fr`, which gave them no fallback at all — merging here
 * means a key missing from it.json shows the English string, like `t()` does.
 */
export function messagesFor(locale: Locale): Record<string, any> {
  const chain = FALLBACKS[locale] ?? FALLBACKS.fr;
  // Least specific first, so the requested locale wins.
  return [...chain].reverse().reduce<Record<string, any>>(
    (acc, candidate) => deepMerge(acc, messages[candidate]),
    {}
  );
}

function deepMerge(
  base: Record<string, any>,
  overlay: Record<string, unknown>
): Record<string, any> {
  const out: Record<string, any> = { ...base };
  for (const [key, value] of Object.entries(overlay ?? {})) {
    out[key] =
      value && typeof value === 'object' && !Array.isArray(value)
        ? deepMerge(out[key] ?? {}, value as Record<string, unknown>)
        : value;
  }
  return out;
}

function getNestedValue(obj: Record<string, unknown> | undefined, path: string): unknown {
  if (!obj) return undefined;
  return path.split('.').reduce<unknown>((acc, part) => {
    if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, obj);
}

/**
 * Get the locale from an Astro URL pathname.
 *
 * Anything outside a locale prefix (the 404 page) is French.
 */
export function getLocaleFromPath(pathname: string): Locale {
  for (const locale of locales) {
    if (pathname.startsWith(`/${locale}/`) || pathname === `/${locale}`) return locale;
  }
  return 'fr';
}

/** BCP 47 tag for a locale — `fr-FR`, `en-GB`, `it-IT`. */
export function localeTag(locale: Locale): string {
  return LOCALE_TAGS[locale] ?? LOCALE_TAGS.fr;
}

/** Open Graph locale — `fr_FR`, `en_GB`, `it_IT`. */
export function ogLocale(locale: Locale): string {
  return localeTag(locale).replace('-', '_');
}

/**
 * Get the equivalent path in another locale.
 *
 * Delegates to the route table: URL segments are translated, not just prefixed
 * (/fr/tournois/ is /en/tournaments/ is /it/tornei/), so a plain prefix swap
 * would 404.
 */
export function getAlternateLocalePath(pathname: string, targetLocale: Locale): string {
  const currentLocale = getLocaleFromPath(pathname);
  if (currentLocale === targetLocale) return pathname;
  return translatePath(pathname, targetLocale);
}
