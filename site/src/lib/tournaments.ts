import { getCollection } from 'astro:content';
import { localeTag, type Locale } from './i18n';

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
  /** The rest of the final standings, after the top 8 (content.config.ts). */
  standings: {
    place: number;
    playerName: string;
    commanderName: string;
    score: string;
    decklistSlug?: string | null;
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
 * Has this tournament happened yet?
 *
 * The date alone is not enough, because the site is built ahead of what it
 * shows. An event that finished tonight still reads `date === today` until the
 * next build, and a date-only test files it under "upcoming" — with its own
 * results hidden, since the same test gates the results block. That is how
 * Endstep #1 went online listed as a future event, top 8 in hand.
 *
 * A filled `top8` is the organiser saying it took place, so it settles the case
 * the date cannot. Announcements have an empty `top8` and stay upcoming until
 * their date passes; a past event whose results were never sent keeps falling
 * through on the date, as before.
 */
export function hasHappened(t: { date: string; top8?: unknown[] }): boolean {
  const today = new Date().toISOString().slice(0, 10);
  return t.date < today || (t.top8?.length ?? 0) > 0;
}

/**
 * Get upcoming tournaments, sorted ASC (nearest first).
 */
export async function getUpcomingTournaments(): Promise<TournamentEntry[]> {
  const all = await getAllTournaments();
  return all
    .filter(t => !hasHappened(t))
    .sort((a, b) => a.date.localeCompare(b.date));
}

/**
 * Get past tournaments, sorted DESC (most recent first).
 */
export async function getPastTournaments(): Promise<TournamentEntry[]> {
  const all = await getAllTournaments();
  return all
    .filter(t => hasHappened(t))
    .sort((a, b) => b.date.localeCompare(a.date));
}

/**
 * Format an ISO date string for display.
 */
export function formatDate(dateStr: string, locale: Locale): string {
  const date = new Date(dateStr + 'T00:00:00');
  return new Intl.DateTimeFormat(localeTag(locale), {
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
