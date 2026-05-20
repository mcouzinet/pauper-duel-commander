import { t, type Locale } from './i18n';

const WUBRG = ['W', 'U', 'B', 'R', 'G'] as const;

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

/**
 * Map color codes to CSS-friendly color values for charts.
 */
export const COLOR_HEX: Record<string, string> = {
  W: '#F8F6F1',
  U: '#0E68AB',
  B: '#150B00',
  R: '#D3202A',
  G: '#00733E',
  C: '#BEB9B2',
  Multi: '#FFA500',
};

/**
 * Sort color counts: count DESC, WUBRG order as tiebreaker.
 */
export function sortColorCounts(counts: Record<string, number>): [string, number][] {
  const order: Record<string, number> = { W: 0, U: 1, B: 2, R: 3, G: 4, C: 5, Multi: 6 };
  return Object.entries(counts).sort(([a, countA], [b, countB]) => {
    if (countB !== countA) return countB - countA;
    return (order[a] ?? 99) - (order[b] ?? 99);
  });
}
