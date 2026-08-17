import { getCardsByNames, getCardByName, getCardImage, getManaCost, getCmc, getColors, getTypeLine, getPrimaryType } from './scryfall';
import { bannedNameSet } from './banlist';
import type { ScryfallCard } from '../types/scryfall';
import type { ParsedCard, EnrichedCard, DeckStats, DeckData } from '../types/decklist';

const TYPE_ORDER: Record<string, number> = {
  Creature: 1,
  Planeswalker: 2,
  Instant: 3,
  Sorcery: 4,
  Artifact: 5,
  Enchantment: 6,
  Land: 7,
  Other: 8,
};

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
      manaCost: getManaCost(data) ?? '',
      colors: getColors(data),
      imageUrl: getCardImage(data, 'normal'),
      imageUrlSmall: getCardImage(data, 'small'),
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
  let nonLandCards = 0;
  const typeCounts: Record<string, number> = {};
  const cmcDistribution: Record<string, number> = { '0': 0, '1': 0, '2': 0, '3': 0, '4': 0, '5': 0, '6': 0, '7+': 0 };
  const colorCounts: Record<string, number> = {};

  for (const card of cards) {
    totalCards += card.quantity;

    // Type counts
    typeCounts[card.type] = (typeCounts[card.type] ?? 0) + card.quantity;

    // CMC distribution (excluding lands)
    if (card.type !== 'Land') {
      const cmcKey = card.cmc >= 7 ? '7+' : String(card.cmc);
      cmcDistribution[cmcKey] = (cmcDistribution[cmcKey] ?? 0) + card.quantity;
      totalCmc += card.cmc * card.quantity;
      nonLandCards += card.quantity;
    }

    // Color counts
    const colors = card.colors;
    if (!colors || colors.length === 0) {
      colorCounts['C'] = (colorCounts['C'] ?? 0) + card.quantity;
    } else if (colors.length > 1) {
      colorCounts['Multi'] = (colorCounts['Multi'] ?? 0) + card.quantity;
    } else {
      colorCounts[colors[0]] = (colorCounts[colors[0]] ?? 0) + card.quantity;
    }
  }

  // Remove zero entries
  for (const key of Object.keys(colorCounts)) {
    if (colorCounts[key] === 0) delete colorCounts[key];
  }

  return {
    totalCards,
    uniqueCards: cards.length,
    typeCounts,
    cmcDistribution,
    colorCounts,
    averageCmc: nonLandCards > 0 ? Math.round((totalCmc / nonLandCards) * 10) / 10 : 0,
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
