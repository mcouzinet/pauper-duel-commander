/**
 * Format a Scryfall mana cost string into HTML with Mana Font icons.
 *
 * Input:  "{2}{U}{U}"
 * Output: '<span class="mana-cost-wrapper"><i class="ms ms-2 ms-cost" title="2"></i>...'
 */
export function formatManaCost(manaCost: string | null | undefined): string {
  if (!manaCost) return '';

  const formatted = manaCost.replace(/\{([^}]+)\}/g, (_match, symbol: string) => {
    let symbolLower = symbol.toLowerCase();

    // Handle hybrid mana: "W/U" → "wu"
    if (symbolLower.includes('/')) {
      symbolLower = symbolLower.replace(/\//g, '');
    }

    // Sanitize for class name
    const manaClass = symbolLower.replace(/[^a-z0-9]/g, '');

    return `<i class="ms ms-${manaClass} ms-cost" title="${escAttr(symbol)}"></i>`;
  });

  return `<span class="mana-cost-wrapper">${formatted}</span>`;
}

/**
 * Format color symbols (e.g., "BRU") into HTML Mana Font icons.
 */
export function formatColorSymbols(colors: string | string[]): string {
  const validColors = ['W', 'U', 'B', 'R', 'G', 'C'];
  const colorStr = Array.isArray(colors) ? colors.join('') : colors;

  return [...colorStr]
    .filter(c => validColors.includes(c.toUpperCase()))
    .map(c => {
      const lower = c.toLowerCase();
      return `<i class="ms ms-${lower} ms-cost ms-shadow" style="font-size: 1.25em;" title="${c.toUpperCase()}"></i>`;
    })
    .join('');
}

function escAttr(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
