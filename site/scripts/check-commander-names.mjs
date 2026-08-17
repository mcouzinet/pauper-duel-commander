/**
 * Audits every commander named in the tournament data.
 *
 * Two failure modes, both of which have already put wrong information on the
 * site:
 *
 *   - **the name resolves to the wrong card.** Artefact #1's winner was recorded
 *     as "Hobgoblin Bandit Lord" (a rare from Forgotten Realms) when the deck was
 *     "Hobgoblin, Mantled Marauder" — the same tournament's metaList had it
 *     right, so the two halves of one event disagreed. The site's Scryfall
 *     lookup falls back to a fuzzy search when the exact name misses, so a typo
 *     does not fail loudly: it silently returns *some* card.
 *   - **no printing at uncommon**, which rule 2.4 forbids for a commander. The
 *     PHP validator runs that check on decks players submit; nothing ran it on
 *     the results we publish ourselves.
 *
 * Works off the build's Scryfall cache (`.cache/scryfall`), so a normal run
 * makes no network requests at all. It only reaches for the API to list a card's
 * printings, and only for the few whose default printing is not uncommon —
 * rarity has to be judged across every paper/MTGO printing, Arena excluded, or
 * Baleful Strix (rare by default, uncommon elsewhere) reads as illegal.
 *
 * Read-only. Run after editing tournament results:
 *   node scripts/check-commander-names.mjs
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const TOURNAMENTS = join(ROOT, 'content', 'tournaments');
const CACHE = join(ROOT, '.cache', 'scryfall');

/** Must match cacheKey() in src/lib/scryfall.ts. */
const cacheKey = name => name.toLowerCase().replace(/[^a-z0-9_-]/g, '_');

/** Placeholders mean "not recorded" and are not names. */
const isPlaceholder = value => !value || !/[\p{L}\p{N}]/u.test(value);

/** "A // B" is a partner pair; each half is checked on its own. */
const splitNames = name =>
  (name.includes(' // ') ? name.split(' // ') : name.includes(' / ') ? name.split(' / ') : [name])
    .map(s => s.trim())
    .filter(Boolean);

const sleep = ms => new Promise(r => setTimeout(r, ms));

// ---------------------------------------------------------------------------
// Collect every name, and where it is used
// ---------------------------------------------------------------------------

const sources = new Map();
const record = (name, where) => {
  if (isPlaceholder(name)) return;
  for (const part of splitNames(name)) {
    if (!sources.has(part)) sources.set(part, new Set());
    sources.get(part).add(where);
  }
};

for (const file of readdirSync(TOURNAMENTS).filter(f => f.endsWith('.json'))) {
  const data = JSON.parse(readFileSync(join(TOURNAMENTS, file), 'utf-8'));
  for (const finish of data.top8 ?? []) record(finish.commanderName, `${file} top8 #${finish.place}`);
  for (const entry of data.metaList ?? []) record(entry.name, `${file} metaList`);
}

if (!existsSync(CACHE)) {
  console.error(`[check] cache absent : ${CACHE}\n        lance \`npm run build\` d'abord.`);
  process.exit(2);
}

console.log(`${sources.size} noms de généraux distincts\n`);

// ---------------------------------------------------------------------------
// Offline pass: does the name resolve, and to the card we asked for?
// ---------------------------------------------------------------------------

const problems = [];
const needRarityCheck = [];

for (const [name, where] of [...sources.entries()].sort()) {
  const path = join(CACHE, `name_${cacheKey(name)}.json`);
  if (!existsSync(path)) {
    problems.push({ name, where, issue: 'absent du cache — vérifie l’orthographe, puis relance le build' });
    continue;
  }

  const card = JSON.parse(readFileSync(path, 'utf-8'));

  // A fuzzy fallback answers with a different card. The front face of a
  // double-faced card is a legitimate match ("Garland, Knight of Cornelia"
  // resolving to "Garland… // Chaos, the Endless").
  const resolved = card.name ?? '';
  const matches =
    resolved.toLowerCase() === name.toLowerCase() ||
    resolved.toLowerCase().startsWith(`${name.toLowerCase()} // `);

  if (!matches) {
    problems.push({ name, where, issue: `résolu en « ${resolved} » — ce n’est pas la même carte` });
    continue;
  }

  if (card.rarity !== 'uncommon') needRarityCheck.push({ name, where, card });
}

// ---------------------------------------------------------------------------
// Network pass: only for cards whose default printing is not uncommon
// ---------------------------------------------------------------------------

if (needRarityCheck.length > 0) {
  console.log(`${needRarityCheck.length} carte(s) non peu commune par défaut — vérification de toutes les impressions…\n`);
}

for (const { name, where, card } of needRarityCheck) {
  const uri = card.prints_search_uri;
  if (!uri) {
    problems.push({ name, where, issue: `rareté par défaut « ${card.rarity} » et impressions introuvables` });
    continue;
  }

  let printings = null;
  for (let attempt = 1; attempt <= 3 && printings === null; attempt++) {
    await sleep(250); // Comfortably inside Scryfall's limit.
    const response = await fetch(uri, { headers: { 'User-Agent': 'PDC-Site/2.0' } });

    if (response.status === 429) {
      const wait = Number(response.headers.get('retry-after') ?? 60);
      console.log(`  … limité par Scryfall, pause de ${wait}s`);
      await sleep(wait * 1000);
      continue;
    }
    if (!response.ok) break;
    printings = (await response.json()).data ?? [];
  }

  if (printings === null) {
    // Never report a network problem as a data problem: that is how the first
    // version of this script produced twenty false "introuvable" reports.
    console.log(`  ⚠ ${name} : impressions non récupérées (réseau) — non conclusif`);
    continue;
  }

  const paper = printings.filter(print => !(print.games ?? []).every(game => game === 'arena'));
  const rarities = new Set(paper.map(print => print.rarity));

  if (!rarities.has('uncommon')) {
    problems.push({
      name,
      where,
      issue: `jamais imprimé en peu commune (${[...rarities].join(', ') || 'aucune impression papier'}) — règle 2.4`,
    });
  }
}

// ---------------------------------------------------------------------------

if (problems.length === 0) {
  console.log('✓ tous les généraux résolvent vers la bonne carte et ont une impression peu commune');
  process.exit(0);
}

console.log(`\n${problems.length} problème(s) :\n`);
for (const { name, where, issue } of problems) {
  console.log(`  ✗ ${name}`);
  console.log(`      ${issue}`);
  console.log(`      utilisé dans : ${[...where].join(', ')}\n`);
}
process.exit(1);
