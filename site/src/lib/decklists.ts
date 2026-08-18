/**
 * Decklists, enriched with everything the UI needs to be useful.
 *
 * The raw collection only knows a commander, a date and a card blob. What makes
 * a list worth clicking is *where it comes from*: four entries called
 * "Gut, True Soul Zealot" are indistinguishable until you say which one won
 * Anim'Magic #3. That link already exists in the data — tournaments reference
 * decklists through `top8[].decklistSlug` — it was simply never read from the
 * decklist side. `getDecklists()` resolves it once and hands every page the same
 * enriched objects.
 *
 * Build-time only (content collections + Scryfall cache).
 */
import { getCollection } from 'astro:content';
import { getCardByName, getCardImage } from './scryfall';
import { colorIdentityKey, mergeColorIdentities } from './colors';
import { bannedNameSet, isCommanderBanned } from './banlist';
import type { Locale } from './i18n';

export interface DecklistResult {
  /** Tournament slug, for linking. */
  slug: string;
  title: string;
  date: string;
  city: string;
  location: string;
  /** 1-based finishing place. */
  place: number;
  score: string;
}

export interface EnrichedDecklist {
  slug: string;
  title: string;
  commander: string;
  partner?: string;
  author?: string;
  archetype?: string;
  date?: string;
  tags: string[];
  /** Raw MTGO text, kept for export. */
  cards: string;
  commanderImage: string | null;
  commanderCardImage: string | null;
  partnerImage: string | null;
  colors: string[];
  ciKey: string;
  isBanned: boolean;
  /** Tournament finish, when this list came from one. */
  result: DecklistResult | null;
}

let cached: Promise<EnrichedDecklist[]> | null = null;

/** Every decklist, enriched, sorted by date DESC. Computed once per build. */
export function getDecklists(): Promise<EnrichedDecklist[]> {
  cached ??= build();
  return cached;
}

async function build(): Promise<EnrichedDecklist[]> {
  const [entries, results] = await Promise.all([
    getCollection('decklists'),
    resultsBySlug(),
  ]);
  const banned = bannedNameSet();

  const decklists = await Promise.all(entries.map(async entry => {
    const d = entry.data;

    const commanderCard = await getCardByName(d.commander);
    const partnerCard = d.partner ? await getCardByName(d.partner) : null;

    const colors = mergeColorIdentities(
      commanderCard?.color_identity ?? [],
      partnerCard?.color_identity ?? [],
    );

    return {
      slug: entry.id,
      title: d.title,
      commander: d.commander,
      partner: d.partner,
      author: d.author,
      archetype: d.archetype,
      date: d.date,
      tags: d.tags ?? [],
      cards: d.cards,
      commanderImage: commanderCard ? getCardImage(commanderCard, 'art_crop') : null,
      commanderCardImage: commanderCard ? getCardImage(commanderCard, 'normal') : null,
      partnerImage: partnerCard ? getCardImage(partnerCard, 'art_crop') : null,
      colors,
      ciKey: colorIdentityKey(colors),
      isBanned:
        isCommanderBanned(d.commander, banned) ||
        (d.partner ? isCommanderBanned(d.partner, banned) : false),
      result: results.get(entry.id) ?? null,
    } satisfies EnrichedDecklist;
  }));

  return decklists.sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));
}

/**
 * Index tournament finishes by decklist slug.
 *
 * A list can in principle be referenced twice; the best (lowest) place wins so
 * a card never advertises a worse finish than the deck actually got.
 */
async function resultsBySlug(): Promise<Map<string, DecklistResult>> {
  const tournaments = await getCollection('tournaments');
  const map = new Map<string, DecklistResult>();

  for (const entry of tournaments) {
    const t = entry.data;
    for (const finish of t.top8) {
      if (!finish.decklistSlug) continue;
      const existing = map.get(finish.decklistSlug);
      if (existing && existing.place <= finish.place) continue;
      map.set(finish.decklistSlug, {
        slug: entry.id,
        title: t.title,
        date: t.date,
        city: t.city,
        location: t.location,
        place: finish.place,
        score: finish.score,
      });
    }
  }

  return map;
}

/** One decklist by slug. */
export async function getDecklist(slug: string): Promise<EnrichedDecklist | null> {
  return (await getDecklists()).find(d => d.slug === slug) ?? null;
}

/**
 * Other lists piloting the same commander (partner ignored, so a Gut deck finds
 * its Gut cousins whatever background they run).
 */
export async function getSiblingDecklists(slug: string, limit = 4): Promise<EnrichedDecklist[]> {
  const all = await getDecklists();
  const self = all.find(d => d.slug === slug);
  if (!self) return [];
  return all
    .filter(d => d.slug !== slug && d.commander === self.commander)
    .slice(0, limit);
}

// ---------------------------------------------------------------------------
// Shelves — answers to "I want to play PDC, what do I build?"
// ---------------------------------------------------------------------------

export interface Shelf {
  /** Stable key, used for the i18n label and the anchor. */
  key: 'moment' | 'performing' | 'new' | 'original';
  decklists: EnrichedDecklist[];
}

/**
 * A decklist's identity in meta terms.
 *
 * Meta lists name a pair as one string — "Commander // Partner" — so a decklist,
 * which keeps the two in separate fields, has to be joined the same way before
 * it can be looked up against them.
 */
function metaKey(commander: string, partner?: string): string {
  return (partner ? `${commander} // ${partner}` : commander).toLowerCase();
}

/**
 * Build the shelves, in reading order, skipping any that would be empty.
 *
 * `originalCommanders` comes from the meta (commanders played exactly once), so
 * "offbeat" means something measured rather than asserted.
 */
export async function getShelves(options: {
  recentTournamentSlugs: string[];
  originalCommanders: Set<string>;
  limit?: number;
}): Promise<Shelf[]> {
  const { recentTournamentSlugs, originalCommanders, limit = 4 } = options;
  const all = await getDecklists();
  const legal = all.filter(d => !d.isBanned);
  const recent = new Set(recentTournamentSlugs);

  // Order matters twice over: the index page opens on the first shelf, and it is
  // the picker's first option. Newest first, so the page leads with what changed.
  const shelves: Shelf[] = [
    {
      key: 'new',
      // getDecklists() sorts newest first, so this is already the recent end.
      decklists: legal.slice(0, limit),
    },
    {
      key: 'moment',
      decklists: legal.filter(d => d.result && recent.has(d.result.slug)).slice(0, limit),
    },
    {
      key: 'performing',
      decklists: legal
        .filter(d => d.result && d.result.place <= 2)
        .sort((a, b) => (b.result!.date).localeCompare(a.result!.date))
        .slice(0, limit),
    },
    {
      key: 'original',
      decklists: legal
        // A tournament meta list keys a pair as one string, "Commander // Partner",
        // while a decklist keeps the two apart. Comparing the bare commander to
        // those keys never matched a partnered deck, so every one of them was
        // silently absent from this shelf.
        .filter(d => originalCommanders.has(metaKey(d.commander, d.partner)))
        .slice(0, limit),
    },
  ];

  return shelves.filter(s => s.decklists.length > 0);
}

/** Distinct archetypes present in the collection, sorted. */
export async function getArchetypes(): Promise<string[]> {
  const all = await getDecklists();
  return [...new Set(all.map(d => d.archetype).filter((a): a is string => Boolean(a)))].sort();
}

/** Distinct color identity keys present in the collection, sorted. */
export async function getColorIdentities(): Promise<string[]> {
  const all = await getDecklists();
  return [...new Set(all.map(d => d.ciKey))].sort();
}

/**
 * Localised ordinal for a finishing place: 1 -> "1er" / "1st" / "1º".
 *
 * Rule-based rather than a translated string: English needs the number to pick
 * its suffix, so a flat catalogue entry could not express it.
 */
export function placeLabel(place: number, locale: Locale): string {
  if (locale === 'fr') return place === 1 ? '1er' : `${place}e`;
  // Italian ordinals take the masculine indicator at every rank.
  if (locale === 'it') return `${place}º`;
  const suffix = place === 1 ? 'st' : place === 2 ? 'nd' : place === 3 ? 'rd' : 'th';
  return `${place}${suffix}`;
}
