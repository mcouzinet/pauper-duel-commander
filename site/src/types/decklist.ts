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
  /** Colours of mana the card can add, from Scryfall's `produced_mana`. */
  producedMana: string[];
  /** On the ban list today, whatever it was on the day the list was played. */
  isBanned: boolean;
}

export interface DeckStats {
  totalCards: number;
  uniqueCards: number;
  typeCounts: Record<string, number>;
  /** Spells only, keyed "0" to "6" then "7+". Lands are not part of the curve. */
  cmcDistribution: Record<string, number>;
  /** Spells only. */
  averageCmc: number;
  averageCmcWithLands: number;
  landCount: number;
  /** Lands expected in a 7-card opening hand drawn from the 99. */
  landsPerHand: number;
  /** Coloured symbols in the mana costs, per copy (a hybrid symbol counts for both colours). */
  colorSymbols: Record<string, number>;
  /** Copies that can produce each colour; `landSources` is the share of them that are lands. */
  colorSources: Record<string, number>;
  landSources: Record<string, number>;
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
