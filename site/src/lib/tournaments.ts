import { getCollection } from 'astro:content';
import type { Locale } from './i18n';

export interface TournamentEntry {
  slug: string;
  title: string;
  date: string;
  location: string;
  city: string;
  playerCount: number;
  actualPlayerCount?: number | null;
  signupUrl?: string;
  details?: string;
  top8: {
    place: number;
    playerName: string;
    commanderName: string;
    score: string;
    decklistSlug: string | null;
  }[];
  metaList: { name: string; count: number }[];
}

/**
 * Get all tournaments from the content collection.
 */
export async function getAllTournaments(): Promise<TournamentEntry[]> {
  const entries = await getCollection('tournaments');
  return entries.map(e => ({
    slug: e.id,
    ...e.data,
  }));
}

/**
 * Get upcoming tournaments (date >= today), sorted ASC (nearest first).
 */
export async function getUpcomingTournaments(): Promise<TournamentEntry[]> {
  const all = await getAllTournaments();
  const today = new Date().toISOString().slice(0, 10);
  return all
    .filter(t => t.date >= today)
    .sort((a, b) => a.date.localeCompare(b.date));
}

/**
 * Get past tournaments (date < today), sorted DESC (most recent first).
 */
export async function getPastTournaments(): Promise<TournamentEntry[]> {
  const all = await getAllTournaments();
  const today = new Date().toISOString().slice(0, 10);
  return all
    .filter(t => t.date < today)
    .sort((a, b) => b.date.localeCompare(a.date));
}

/**
 * Format an ISO date string for display.
 */
export function formatDate(dateStr: string, locale: Locale): string {
  const date = new Date(dateStr + 'T00:00:00');
  return new Intl.DateTimeFormat(locale === 'fr' ? 'fr-FR' : 'en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date);
}

/**
 * Split a commander name that may contain partners: "A // B" → ["A", "B"]
 */
export function splitCommanderName(name: string): string[] {
  // Try " // " first (standard separator)
  if (name.includes(' // ')) {
    return name.split(' // ').map(s => s.trim()).filter(Boolean);
  }
  // Try " / " as shorthand (with spaces to avoid matching card names with /)
  if (name.includes(' / ')) {
    return name.split(' / ').map(s => s.trim()).filter(Boolean);
  }
  return [name.trim()];
}

/**
 * Expand an array of commander names (possibly partner pairs) into individual card names.
 */
export function expandCommanderNames(names: string[]): string[] {
  return names.flatMap(splitCommanderName);
}

const WUBRG_ORDER: Record<string, number> = { W: 0, U: 1, B: 2, R: 3, G: 4 };

/**
 * Sort color counts by count desc, WUBRG order as tiebreaker.
 */
export function sortColorCounts(counts: Record<string, number>): [string, number][] {
  return Object.entries(counts).sort(([a, countA], [b, countB]) => {
    if (countB !== countA) return countB - countA;
    return (WUBRG_ORDER[a] ?? 99) - (WUBRG_ORDER[b] ?? 99);
  });
}

/**
 * Is this field a placeholder rather than a value?
 *
 * The WordPress migration recorded unknown players and commanders as "???", and
 * the site treated that as a name: it looked it up on Scryfall, whose fuzzy
 * fallback matched `_____` (an Unhinged card), so an unrecorded commander was
 * illustrated with a silver-bordered joke card and counted as a real deck in the
 * metagame.
 *
 * Anything with no letter or digit in it is a placeholder, which covers "???",
 * "?", "-", "--" and the empty string without a list to maintain.
 */
export function isPlaceholder(value: string | null | undefined): boolean {
  return !value || !/[\p{L}\p{N}]/u.test(value);
}
