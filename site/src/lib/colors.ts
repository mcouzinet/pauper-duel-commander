import { t, type Locale } from './i18n';

/**
 * Compute the color identity key from an array of color codes.
 * Sorts alphabetically and joins: ["R", "B"] → "BR"
 */
export function colorIdentityKey(colors: string[]): string {
  if (!colors || colors.length === 0) return 'C';
  const sorted = [...new Set(colors)].sort();
  return sorted.join('');
}

/**
 * Merge color identities from multiple sources (e.g., commander + partner).
 */
export function mergeColorIdentities(...identities: string[][]): string[] {
  const merged = new Set<string>();
  for (const id of identities) {
    for (const color of id) merged.add(color);
  }
  return [...merged].sort();
}

/**
 * Get the localized label for a color identity key.
 */
export function colorIdentityLabel(key: string, locale: Locale): string {
  return t(`ci.${key}`, locale) || key;
}

/**
 * Get the localized label for a single color.
 */
export function colorLabel(code: string, locale: Locale): string {
  return t(`color.${code}`, locale) || code;
}
