import fr from '../i18n/fr.json';
import en from '../i18n/en.json';
import { translatePath } from './routes';

const messages: Record<string, Record<string, unknown>> = { fr, en };

export type Locale = 'fr' | 'en';

/**
 * Get a translated string by dot-notation key.
 * Falls back to French if key is missing in target locale.
 */
export function t(key: string, locale: Locale = 'fr'): string {
  const value = getNestedValue(messages[locale], key)
    ?? getNestedValue(messages['fr'], key)
    ?? key;
  return String(value);
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
 */
export function getLocaleFromPath(pathname: string): Locale {
  if (pathname.startsWith('/en/') || pathname === '/en') return 'en';
  return 'fr';
}

/**
 * Get the equivalent path in the other locale.
 *
 * Delegates to the route table: URL segments are translated, not just prefixed
 * (/fr/tournois/ is /en/tournaments/), so a plain prefix swap would 404.
 */
export function getAlternateLocalePath(pathname: string, targetLocale: Locale): string {
  const currentLocale = getLocaleFromPath(pathname);
  if (currentLocale === targetLocale) return pathname;
  return translatePath(pathname, targetLocale);
}

/** Available locales */
export const locales: Locale[] = ['fr', 'en'];
