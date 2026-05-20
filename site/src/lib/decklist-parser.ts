export interface ParsedCard {
  quantity: number;
  name: string;
}

/**
 * Parse MTGO-format decklist text into structured cards.
 */
export function parseDecklist(text: string): ParsedCard[] {
  if (!text) return [];
  return text
    .split('\n')
    .map(parseLine)
    .filter((card): card is ParsedCard => card !== null);
}

function parseLine(line: string): ParsedCard | null {
  line = line.trim();
  if (!line) return null;
  if (/^(\/\/|#)/.test(line)) return null;
  if (/^sideboard:?$/i.test(line)) return null;

  const match = line.match(/^(\d+)\s+(.+)$/);
  if (match) {
    const quantity = parseInt(match[1], 10);
    const name = match[2].trim();
    if (quantity > 0 && name) return { quantity, name };
  }

  // Fallback: line without quantity → assume 1 copy
  if (/^[A-Za-z]/.test(line)) return { quantity: 1, name: line };

  return null;
}

/**
 * Normalize a card name: trim, collapse spaces, normalize " // " separator.
 */
export function normalizeCardName(name: string): string {
  return name.trim().replace(/\s+/g, ' ').replace(/\s*\/\/\s*/g, ' // ');
}

/**
 * Format parsed cards back to MTGO text.
 */
export function formatToMtgo(cards: ParsedCard[]): string {
  return cards.map(c => `${c.quantity} ${c.name}`).join('\n');
}

/**
 * Format to Moxfield export format.
 */
export function formatToMoxfield(cards: ParsedCard[], commander: string, partner?: string): string {
  const lines: string[] = [];

  if (commander) {
    lines.push('Commander');
    lines.push(`1 ${commander}`);
    if (partner) lines.push(`1 ${partner}`);
    lines.push('');
  }

  if (cards.length > 0) {
    lines.push('Deck');
    for (const card of cards) {
      lines.push(`${card.quantity} ${card.name}`);
    }
  }

  return lines.join('\n');
}

/**
 * Extract unique card names from parsed cards.
 */
export function getCardNames(cards: ParsedCard[]): string[] {
  return cards.map(c => c.name);
}

/**
 * Count total cards (sum of quantities).
 */
export function countCards(cards: ParsedCard[]): number {
  return cards.reduce((sum, c) => sum + c.quantity, 0);
}
