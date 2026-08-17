/**
 * Ban list access.
 *
 * `content/banlist.json` was read with readFileSync in five different pages,
 * each rebuilding its own lowercase Set. Everything goes through here now, so
 * "is this card banned?" has exactly one answer on the site — and the display
 * lists, the announcement history and the freshness date come from one place.
 *
 * Build-time only (node:fs).
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { getCollection } from 'astro:content';
import type { Locale } from './i18n';

export interface Banlist {
  /** ISO date of the last official announcement. */
  lastUpdated: string;
  bannedAsCommander: string[];
  bannedInDeck: string[];
  /** Union of both lists — the only one the PHP validator reads. */
  cards: string[];
}

export interface BanlistChange {
  card: string;
  type: 'banned' | 'unbanned' | 'restricted';
  experimental: boolean;
}

export interface Announcement {
  date: string;
  source: string;
  kind: 'initial' | 'update';
  changes: BanlistChange[];
  notes: { fr: string; en: string }[];
}

let cached: Banlist | null = null;

/** The ban list, read once per build. */
export function getBanlist(): Banlist {
  if (cached) return cached;

  const raw = JSON.parse(
    readFileSync(join(process.cwd(), 'content', 'banlist.json'), 'utf-8'),
  ) as Partial<Banlist>;

  cached = {
    lastUpdated: raw.lastUpdated ?? '',
    bannedAsCommander: raw.bannedAsCommander ?? [],
    bannedInDeck: raw.bannedInDeck ?? [],
    cards: raw.cards ?? [],
  };
  return cached;
}

/** Lowercased names of every banned card, for membership tests. */
export function bannedNameSet(): Set<string> {
  return new Set(getBanlist().cards.map(name => name.toLowerCase()));
}

/**
 * Is this commander banned?
 *
 * Handles partner pairs ("A // B") and split names: a pair is banned as soon as
 * either half is, which is how the decklist and tournament pages already
 * treated it.
 */
export function isCommanderBanned(name: string, banned = bannedNameSet()): boolean {
  return splitNames(name).some(part => banned.has(part.toLowerCase()));
}

/** Split "A // B" or "A / B" into individual card names. */
export function splitNames(name: string): string[] {
  const separator = name.includes(' // ') ? ' // ' : name.includes(' / ') ? ' / ' : null;
  if (!separator) return [name.trim()].filter(Boolean);
  return name.split(separator).map(s => s.trim()).filter(Boolean);
}

/** Announcements, newest first. */
export async function getAnnouncements(): Promise<Announcement[]> {
  const entries = await getCollection('banlistHistory');
  return entries
    .map(e => e.data as Announcement)
    .sort((a, b) => (a.date < b.date ? 1 : -1));
}

/** The most recent announcement, or null when the history is empty. */
export async function getLastAnnouncement(): Promise<Announcement | null> {
  const all = await getAnnouncements();
  return all[0] ?? null;
}

/**
 * Cards touched by the most recent announcement, lowercased.
 *
 * Drives the "Nouveau" badge on the ban list grid: a player who already knows
 * the list needs to see what moved, not re-read seventeen cards.
 */
export async function recentlyChangedNames(): Promise<Set<string>> {
  const last = await getLastAnnouncement();
  if (!last || last.kind === 'initial') return new Set();
  return new Set(last.changes.map(c => c.card.toLowerCase()));
}

/** Effective freshness date: the announcement history wins over `lastUpdated`. */
export async function getBanlistDate(): Promise<string> {
  const last = await getLastAnnouncement();
  return last?.date ?? getBanlist().lastUpdated;
}

/** Format an ISO date for display in the given locale. */
export function formatBanlistDate(iso: string, locale: Locale): string {
  if (!iso) return '';
  return new Date(iso + 'T00:00:00').toLocaleDateString(locale === 'fr' ? 'fr-FR' : 'en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

/**
 * One-line summary of the latest announcement, for the home page tile.
 *
 * Returns null when there is nothing to say (no history, or the initial list —
 * "20 cards banned" is not news). Names a single card outright; counts beyond
 * that, because three card names do not fit on a tile.
 */
export async function summariseLastAnnouncement(
  locale: Locale,
): Promise<{ date: string; summary: string } | null> {
  const last = await getLastAnnouncement();
  if (!last || last.kind === 'initial' || last.changes.length === 0) return null;

  const banned = last.changes.filter(c => c.type === 'banned');
  const unbanned = last.changes.filter(c => c.type === 'unbanned');
  const restricted = last.changes.filter(c => c.type === 'restricted');

  const parts: string[] = [];
  const describe = (
    cards: BanlistChange[],
    one: (card: string) => string,
    many: (n: number) => string,
  ) => {
    if (cards.length === 0) return;
    parts.push(cards.length === 1 ? one(cards[0].card) : many(cards.length));
  };

  if (locale === 'fr') {
    describe(banned, card => `${card} bannie`, n => `${n} cartes bannies`);
    describe(unbanned, card => `${card} légalisée`, n => `${n} légalisées`);
    describe(restricted, card => `${card} restreinte`, n => `${n} restreintes`);
  } else {
    describe(banned, card => `${card} banned`, n => `${n} cards banned`);
    describe(unbanned, card => `${card} unbanned`, n => `${n} unbanned`);
    describe(restricted, card => `${card} restricted`, n => `${n} restricted`);
  }

  return { date: last.date, summary: parts.join(' · ') };
}
