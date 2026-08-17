import type { ScryfallCard } from './scryfall';

export interface ParsedCard {
  quantity: number;
  name: string;
}

export interface EnrichedCard extends ParsedCard {
  scryfallData: ScryfallCard | null;
  cmc: number;
  type: string;
  typeLine: string;
  manaCost: string;
  colors: string[];
  imageUrl: string | null;
  imageUrlSmall: string | null;
  /** On the ban list today, whatever it was on the day the list was played. */
  isBanned: boolean;
}

export interface DeckStats {
  totalCards: number;
  uniqueCards: number;
  typeCounts: Record<string, number>;
  cmcDistribution: Record<string, number>;
  colorCounts: Record<string, number>;
  averageCmc: number;
}

export interface DeckData {
  cards: EnrichedCard[];
  cardsByType: Record<string, EnrichedCard[]>;
  commander: {
    name: string;
    scryfallData: ScryfallCard | null;
    imageUrl: string | null;
    imageUrlArtCrop: string | null;
    manaCost: string | null;
    typeLine: string | null;
  } | null;
  partner: {
    name: string;
    scryfallData: ScryfallCard | null;
    imageUrl: string | null;
    manaCost: string | null;
    typeLine: string | null;
  } | null;
  stats: DeckStats;
  /** Cards in the 99 that have since been banned, in list order. */
  bannedCards: EnrichedCard[];
}
