import { getCardsByNames, getCardByName, getCardImage, getManaCost, getCmc, getColors, getTypeLine, getPrimaryType } from './scryfall';
import { bannedNameSet } from './banlist';
import type { ScryfallCard } from '../types/scryfall';
import type { ParsedCard, EnrichedCard, DeckStats, DeckData } from '../types/decklist';

/** i18n key of each primary type's group label. */
export const TYPE_LABEL_KEYS: Record<string, string> = {
  Creature: 'common.creatures',
  Planeswalker: 'common.planeswalkers',
  Instant: 'common.instants',
  Sorcery: 'common.sorceries',
  Artifact: 'common.artifacts',
  Enchantment: 'common.enchantments',
  Land: 'common.lands',
  Other: 'common.other',
};

export const TYPE_ORDER: Record<string, number> = {
  Creature: 1,
  Planeswalker: 2,
  Instant: 3,
  Sorcery: 4,
  Artifact: 5,
  Enchantment: 6,
  Land: 7,
  Other: 8,
};

const COLORS = ['W', 'U', 'B', 'R', 'G'] as const;

/**
 * The cost printed on the front face. Scryfall gives adventure and split cards
 * both costs ("{3}{R} // {1}{R}"), which in a list row squeezed the name down to
 * "Gr…": the card is cast for its front cost, and that is the one to read.
 */
function frontFaceCost(manaCost: string): string {
  return manaCost.split(' // ')[0];
}

/**
 * Fetch Scryfall data for all cards and enrich them.
 */
export async function fetchCardData(parsedCards: ParsedCard[]): Promise<EnrichedCard[]> {
  const names = [...new Set(parsedCards.map(c => c.name))];
  const cardsMap = await getCardsByNames(names);
  const banned = bannedNameSet();

  return parsedCards.map(card => {
    const data = cardsMap.get(card.name.toLowerCase()) ?? null;
    return {
      quantity: card.quantity,
      name: card.name,
      scryfallData: data,
      cmc: getCmc(data),
      type: getPrimaryType(data),
      typeLine: getTypeLine(data) ?? 'Unknown',
      manaCost: frontFaceCost(getManaCost(data) ?? ''),
      colors: getColors(data),
      imageUrl: getCardImage(data, 'normal'),
      imageUrlSmall: getCardImage(data, 'small'),
      producedMana: data?.produced_mana ?? [],
      isBanned: banned.has(card.name.toLowerCase()),
    };
  });
}

/**
 * Sort cards by type order, then CMC, then name.
 */
export function sortCards(cards: EnrichedCard[]): EnrichedCard[] {
  return [...cards].sort((a, b) => {
    const typeA = TYPE_ORDER[a.type] ?? 99;
    const typeB = TYPE_ORDER[b.type] ?? 99;
    if (typeA !== typeB) return typeA - typeB;
    if (a.cmc !== b.cmc) return a.cmc - b.cmc;
    return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
  });
}

/**
 * Group cards by type, sorted by TYPE_ORDER.
 */
export function groupByType(cards: EnrichedCard[]): Record<string, EnrichedCard[]> {
  const grouped: Record<string, EnrichedCard[]> = {};
  for (const card of cards) {
    (grouped[card.type] ??= []).push(card);
  }
  // Sort groups by type order
  const sorted: Record<string, EnrichedCard[]> = {};
  const keys = Object.keys(grouped).sort((a, b) => (TYPE_ORDER[a] ?? 99) - (TYPE_ORDER[b] ?? 99));
  for (const key of keys) sorted[key] = grouped[key];
  return sorted;
}

/**
 * Calculate deck statistics.
 */
export function calculateStats(cards: EnrichedCard[]): DeckStats {
  let totalCards = 0;
  let totalCmc = 0;
  let spellCmc = 0;
  let spellCards = 0;
  const typeCounts: Record<string, number> = {};
  const cmcDistribution: Record<string, number> = { '0': 0, '1': 0, '2': 0, '3': 0, '4': 0, '5': 0, '6': 0, '7+': 0 };
  const colorSymbols: Record<string, number> = {};
  const colorSources: Record<string, number> = {};
  const landSources: Record<string, number> = {};
  const add = (counts: Record<string, number>, key: string, n: number) => { counts[key] = (counts[key] ?? 0) + n; };

  for (const card of cards) {
    const n = card.quantity;
    const isLand = card.type === 'Land';
    totalCards += n;
    totalCmc += card.cmc * n;
    add(typeCounts, card.type, n);

    if (!isLand) {
      add(cmcDistribution, card.cmc >= 7 ? '7+' : String(card.cmc), n);
      spellCmc += card.cmc * n;
      spellCards += n;
    }

    // "{W/U}" counts for both colours, "{R/P}" for red, "{2}" for none.
    for (const symbol of card.manaCost.match(/\{[^}]+\}/g) ?? []) {
      for (const color of COLORS) if (symbol.includes(color)) add(colorSymbols, color, n);
    }

    for (const color of card.producedMana) {
      if (!(COLORS as readonly string[]).includes(color)) continue;
      add(colorSources, color, n);
      if (isLand) add(landSources, color, n);
    }
  }

  const round1 = (value: number) => Math.round(value * 10) / 10;
  const landCount = typeCounts.Land ?? 0;

  return {
    totalCards,
    uniqueCards: cards.length,
    typeCounts,
    cmcDistribution,
    averageCmc: spellCards > 0 ? round1(spellCmc / spellCards) : 0,
    averageCmcWithLands: totalCards > 0 ? round1(totalCmc / totalCards) : 0,
    landCount,
    landsPerHand: totalCards > 0 ? round1((7 * landCount) / totalCards) : 0,
    colorSymbols,
    colorSources,
    landSources,
  };
}

/**
 * Prepare full deck data for rendering.
 */
export async function prepareDeckData(
  parsedCards: ParsedCard[],
  commanderName: string,
  partnerName?: string
): Promise<DeckData> {
  const enriched = await fetchCardData(parsedCards);
  const sorted = sortCards(enriched);
  const cardsByType = groupByType(sorted);
  const stats = calculateStats(enriched);
  const bannedCards = sorted.filter(card => card.isBanned);

  // Fetch commander
  let commander: DeckData['commander'] = null;
  if (commanderName) {
    const data = await getCardByName(commanderName);
    if (data) {
      commander = {
        name: commanderName,
        scryfallData: data,
        imageUrl: getCardImage(data, 'normal'),
        imageUrlArtCrop: getCardImage(data, 'art_crop'),
        manaCost: getManaCost(data),
        typeLine: getTypeLine(data),
      };
    }
  }

  // Fetch partner
  let partner: DeckData['partner'] = null;
  if (partnerName) {
    const data = await getCardByName(partnerName);
    if (data) {
      partner = {
        name: partnerName,
        scryfallData: data,
        imageUrl: getCardImage(data, 'normal'),
        manaCost: getManaCost(data),
        typeLine: getTypeLine(data),
      };
    }
  }

  return { cards: sorted, cardsByType, commander, partner, stats, bannedCards };
}
