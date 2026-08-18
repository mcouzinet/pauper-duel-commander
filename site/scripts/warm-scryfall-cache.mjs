/**
 * Pre-fills the build-time Scryfall cache before Astro runs.
 *
 * Why this exists: `src/lib/scryfall.ts` throttles itself to one request per
 * 100 ms with a module-level timestamp, but Astro renders pages concurrently, so
 * during a build that throttle does not actually serialise anything. Scryfall
 * starts answering 429, `getCardsByNames()` maps those names to null and caches
 * nothing — the cards render with an empty placeholder instead of their art, no
 * error is reported, and the next build fails exactly the same way.
 *
 * It bites hardest on the 30-day TTL: entries that expire are treated like
 * missing ones, so a build a month later silently loses illustrations for cards
 * that had been fine for weeks.
 *
 * This script asks for everything the content references, strictly one batch at
 * a time, which Scryfall serves happily. Cards already cached and still fresh
 * cost nothing.
 *
 * Runs automatically on predev/prebuild.
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const CACHE = join(ROOT, '.cache', 'scryfall');
const TTL_MS = 30 * 24 * 60 * 60 * 1000;
const BATCH = 75;                       // Scryfall's documented maximum

/** Must match cacheKey() in src/lib/scryfall.ts. */
const cacheKey = name => name.toLowerCase().replace(/[^a-z0-9_-]/g, '_');
const sleep = ms => new Promise(r => setTimeout(r, ms));

function collectNames() {
  const names = new Set();
  const add = value => { if (value && value.trim()) names.add(value.trim()); };

  const tournaments = join(ROOT, 'content', 'tournaments');
  for (const file of readdirSync(tournaments)) {
    const data = JSON.parse(readFileSync(join(tournaments, file), 'utf-8'));
    const entries = [...data.top8.map(t => t.commanderName), ...data.metaList.map(m => m.name)];
    // "Commander // Partner" is fetched as two separate cards.
    for (const entry of entries) if (entry) entry.split('//').forEach(add);
  }

  const decklists = join(ROOT, 'content', 'decklists');
  for (const file of readdirSync(decklists)) {
    const data = JSON.parse(readFileSync(join(decklists, file), 'utf-8'));
    add(data.commander);
    add(data.partner);
    for (const line of (data.cards ?? '').split(/\r?\n/)) {
      const match = line.trim().match(/^\d+\s+(.+)$/);
      if (match) add(match[1]);
    }
  }

  // The ban list renders a scan per card, and each announcement previews the
  // cards it moved. Both were fetched cold at build time, which is exactly the
  // parallel burst Scryfall rate-limits — silently, leaving cards art-less.
  JSON.parse(readFileSync(join(ROOT, 'content', 'banlist.json'), 'utf-8')).cards.forEach(add);

  const history = join(ROOT, 'content', 'banlist-history');
  for (const file of readdirSync(history)) {
    const data = JSON.parse(readFileSync(join(history, file), 'utf-8'));
    for (const change of data.changes ?? []) add(change.card);
  }

  return [...names];
}

function needsFetch(name) {
  const path = join(CACHE, `name_${cacheKey(name)}.json`);
  if (!existsSync(path)) return true;
  try {
    return Date.now() - statSync(path).mtimeMs > TTL_MS;   // expired reads as missing
  } catch {
    return true;
  }
}

const all = collectNames();
const wanted = all.filter(needsFetch);

if (wanted.length === 0) {
  console.log(`[scryfall] ${all.length} cartes, cache à jour`);
  process.exit(0);
}

mkdirSync(CACHE, { recursive: true });
console.log(`[scryfall] ${all.length} cartes référencées, ${wanted.length} à récupérer`);

let written = 0;
const notFound = [];

for (let i = 0; i < wanted.length; ) {
  const batch = wanted.slice(i, i + BATCH);
  let res;
  try {
    res = await fetch('https://api.scryfall.com/cards/collection', {
      method: 'POST',
      headers: { 'User-Agent': 'PDC-Site/2.0', 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifiers: batch.map(name => ({ name })) }),
    });
  } catch (err) {
    console.warn(`[scryfall] réseau indisponible (${err.message}) — cache laissé en l'état`);
    break;
  }

  if (res.status === 429) {
    await sleep(5000);
    continue;                            // same batch, Scryfall asked us to wait
  }
  if (!res.ok) {
    console.warn(`[scryfall] HTTP ${res.status} — lot ignoré`);
    i += BATCH;
    continue;
  }

  const data = await res.json();
  for (const card of data.data ?? []) {
    writeFileSync(join(CACHE, `name_${cacheKey(card.name)}.json`), JSON.stringify(card));
    written++;
    // Double-faced cards answer under their full "A // B" name; alias the half
    // we asked for so the next lookup hits.
    const asked = batch.find(n => card.name.toLowerCase().startsWith(n.toLowerCase()));
    if (asked && cacheKey(asked) !== cacheKey(card.name)) {
      writeFileSync(join(CACHE, `name_${cacheKey(asked)}.json`), JSON.stringify(card));
    }
  }
  for (const nf of data.not_found ?? []) notFound.push(nf.name);

  i += BATCH;
  await sleep(300);
}

console.log(`[scryfall] ${written} cartes mises en cache`);
if (notFound.length) {
  // Not fatal: the page renders without art. Usually a typo in the content.
  console.warn(`[scryfall] introuvables (${notFound.length}) : ${notFound.join(', ')}`);
}
