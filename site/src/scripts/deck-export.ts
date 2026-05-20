/**
 * Deck export functionality (MTGO + Moxfield formats)
 * Reads data from a hidden <script id="deck-export-data"> JSON block.
 */
interface ExportData {
  commander: string;
  partner?: string;
  cards: { quantity: number; name: string }[];
}

function getExportData(): ExportData | null {
  const el = document.getElementById('deck-export-data');
  if (!el?.textContent) return null;
  try {
    return JSON.parse(el.textContent);
  } catch {
    return null;
  }
}

function download(filename: string, content: string) {
  const blob = new Blob([content], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

document.getElementById('export-mtgo')?.addEventListener('click', () => {
  const data = getExportData();
  if (!data) return;
  const lines = data.cards.map(c => `${c.quantity} ${c.name}`);
  download('deck-mtgo.txt', lines.join('\n'));
});

document.getElementById('export-moxfield')?.addEventListener('click', () => {
  const data = getExportData();
  if (!data) return;
  const lines: string[] = ['Commander'];
  lines.push(`1 ${data.commander}`);
  if (data.partner) lines.push(`1 ${data.partner}`);
  lines.push('', 'Deck');
  data.cards.forEach(c => lines.push(`${c.quantity} ${c.name}`));
  download('deck-moxfield.txt', lines.join('\n'));
});
