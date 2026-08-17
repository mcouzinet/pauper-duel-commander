/**
 * Metagame aggregation.
 *
 * The Méta page computed all of this inline, which meant the home page could not
 * show a five-line summary without duplicating seventy lines of reducer. The
 * aggregation lives here now and both pages read the same numbers.
 *
 * Two things the previous version never surfaced and that the data supports:
 *   - the **sample window** (first and last tournament date), so a reader knows
 *     what "9 tournaments" actually covers;
 *   - a **trend**, comparing the most recent tournaments against everything
 *     before them, so the page says something about *now* and not just about the
 *     accumulated total.
 *
 * Build-time only.
 */
import { getCollection } from 'astro:content';
import { getCardsByNames, getCardImage } from './scryfall';
import { bannedNameSet, splitNames } from './banlist';
import type { ScryfallCard } from '../types/scryfall';

/** How many of the newest tournaments count as "recent" for the trend. */
const RECENT_WINDOW = 3;

export interface CommanderStat {
  rank: number;
  /** Full name as recorded, partner pair included. */
  name: string;
  mainName: string;
  partnerName: string | null;
  count: number;
  percentage: number;
  image: string | null;
  cardImage: string | null;
  colors: string[];
  ciKey: string;
  isBanned: boolean;
  /** Percentage-point change vs the pre-window baseline; null when unknown. */
  trend: number | null;
}

export interface Aggregate {
  commanders: CommanderStat[];
  colorCounts: Record<string, number>;
  ciCounts: Record<string, number>;
  tournamentCount: number;
  total: number;
}

export interface MetaData {
  /** Every metaList entry across past tournaments. */
  global: Aggregate;
  /** Commanders that reached places 1–4. */
  top4: Aggregate;
  /** Oldest and newest tournament contributing to `global`. */
  from: string;
  to: string;
  /** Slugs of the tournaments in the recent window, newest first. */
  recentSlugs: string[];
  /** Lowercased names of commanders played exactly once. */
  originalCommanders: Set<string>;
  /** Date of the most recent published tournament (any kind). */
  lastTournamentDate: string;
}

let cached: Promise<MetaData> | null = null;

/** The full metagame picture, computed once per build. */
export function getMeta(): Promise<MetaData> {
  cached ??= build();
  return cached;
}

async function build(): Promise<MetaData> {
  const entries = await getCollection('tournaments');
  const today = new Date().toISOString().slice(0, 10);

  const past = entries
    .map(e => ({ slug: e.id, ...e.data }))
    .filter(t => t.date < today)
    .sort((a, b) => b.date.localeCompare(a.date));

  const withMeta = past.filter(t => t.metaList.length > 0);
  const recentSlugs = withMeta.slice(0, RECENT_WINDOW).map(t => t.slug);
  const recent = new Set(recentSlugs);

  // Raw counts: overall, and split recent / baseline for the trend.
  const globalCounts = new Map<string, { name: string; count: number }>();
  const recentCounts = new Map<string, number>();
  const baselineCounts = new Map<string, number>();
  let globalTotal = 0;
  let recentTotal = 0;
  let baselineTotal = 0;

  for (const tournament of withMeta) {
    const isRecent = recent.has(tournament.slug);
    for (const entry of tournament.metaList) {
      const key = entry.name.toLowerCase();
      const bucket = globalCounts.get(key) ?? { name: entry.name, count: 0 };
      bucket.count += entry.count;
      globalCounts.set(key, bucket);
      globalTotal += entry.count;

      if (isRecent) {
        recentCounts.set(key, (recentCounts.get(key) ?? 0) + entry.count);
        recentTotal += entry.count;
      } else {
        baselineCounts.set(key, (baselineCounts.get(key) ?? 0) + entry.count);
        baselineTotal += entry.count;
      }
    }
  }

  const top4Counts = new Map<string, { name: string; count: number }>();
  let top4Total = 0;
  let top4TournamentCount = 0;

  for (const tournament of past) {
    const finishes = tournament.top8.filter(e => e.place >= 1 && e.place <= 4);
    if (finishes.length === 0) continue;
    top4TournamentCount++;
    for (const finish of finishes) {
      const key = finish.commanderName.toLowerCase();
      const bucket = top4Counts.get(key) ?? { name: finish.commanderName, count: 0 };
      bucket.count += 1;
      top4Counts.set(key, bucket);
      top4Total += 1;
    }
  }

  // One batched Scryfall lookup for every name either ranking needs.
  const names = [...globalCounts.values(), ...top4Counts.values()]
    .flatMap(c => splitNames(c.name));
  const cards = await getCardsByNames([...new Set(names)]);
  const banned = bannedNameSet();

  /** Trend in percentage points, only when both windows have enough data. */
  const trendFor = (key: string): number | null => {
    if (recentTotal === 0 || baselineTotal === 0) return null;
    const now = ((recentCounts.get(key) ?? 0) / recentTotal) * 100;
    const before = ((baselineCounts.get(key) ?? 0) / baselineTotal) * 100;
    const delta = Math.round(now - before);
    return delta === 0 ? 0 : delta;
  };

  const global = aggregate([...globalCounts.entries()], globalTotal, withMeta.length, cards, banned, trendFor);
  const top4 = aggregate([...top4Counts.entries()], top4Total, top4TournamentCount, cards, banned, () => null);

  const dates = withMeta.map(t => t.date).sort();

  return {
    global,
    top4,
    from: dates[0] ?? '',
    to: dates[dates.length - 1] ?? '',
    recentSlugs,
    originalCommanders: new Set(
      [...globalCounts.entries()].filter(([, c]) => c.count === 1).map(([key]) => key),
    ),
    lastTournamentDate: past[0]?.date ?? '',
  };
}

function aggregate(
  counts: [string, { name: string; count: number }][],
  total: number,
  tournamentCount: number,
  cards: Map<string, ScryfallCard | null>,
  banned: Set<string>,
  trendFor: (key: string) => number | null,
): Aggregate {
  const colorCounts: Record<string, number> = {};
  const ciCounts: Record<string, number> = {};

  const sorted = counts.sort((a, b) => b[1].count - a[1].count);

  const commanders = sorted.map(([key, entry], i) => {
    const parts = splitNames(entry.name);
    const mainCard = cards.get(parts[0]?.toLowerCase() ?? '') ?? null;
    const partnerCard = parts.length > 1 ? cards.get(parts[1].toLowerCase()) ?? null : null;

    const colors = [
      ...new Set([...(mainCard?.color_identity ?? []), ...(partnerCard?.color_identity ?? [])]),
    ].sort();
    const ciKey = colors.length > 0 ? colors.join('') : 'C';

    for (const c of colors) colorCounts[c] = (colorCounts[c] ?? 0) + entry.count;
    if (colors.length === 0) colorCounts.C = (colorCounts.C ?? 0) + entry.count;
    ciCounts[ciKey] = (ciCounts[ciKey] ?? 0) + entry.count;

    return {
      rank: i + 1,
      name: entry.name,
      mainName: parts[0] ?? entry.name,
      partnerName: parts.length > 1 ? parts[1] : null,
      count: entry.count,
      percentage: total > 0 ? Math.round((entry.count / total) * 100) : 0,
      image: mainCard ? getCardImage(mainCard, 'art_crop') : null,
      cardImage: mainCard ? getCardImage(mainCard, 'normal') : null,
      colors,
      ciKey,
      isBanned: parts.some(p => banned.has(p.toLowerCase())),
      trend: trendFor(key),
    } satisfies CommanderStat;
  });

  return { commanders, colorCounts, ciCounts, tournamentCount, total };
}
