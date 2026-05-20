import { readFileSync, writeFileSync, existsSync, mkdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import type { ScryfallCard, ImageSize } from '../types/scryfall';

const API_BASE = 'https://api.scryfall.com';
const CACHE_DIR = join(process.cwd(), '.cache', 'scryfall');
const CACHE_DURATION_MS = 30 * 24 * 60 * 60 * 1000; // 30 days
const RATE_LIMIT_MS = 100; // Scryfall asks for 50-100ms between requests

let lastRequestTime = 0;

// ---------------------------------------------------------------------------
// Cache helpers
// ---------------------------------------------------------------------------

function cacheKey(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9_-]/g, '_');
}

function readCache(key: string): ScryfallCard | null {
  const path = join(CACHE_DIR, `${key}.json`);
  if (!existsSync(path)) return null;
  const age = Date.now() - statSync(path).mtimeMs;
  if (age > CACHE_DURATION_MS) return null;
  try {
    return JSON.parse(readFileSync(path, 'utf-8')) as ScryfallCard;
  } catch {
    return null;
  }
}

function writeCache(key: string, data: ScryfallCard): void {
  if (!existsSync(CACHE_DIR)) mkdirSync(CACHE_DIR, { recursive: true });
  writeFileSync(join(CACHE_DIR, `${key}.json`), JSON.stringify(data));
}

// ---------------------------------------------------------------------------
// Rate-limited HTTP
// ---------------------------------------------------------------------------

async function rateLimitedFetch(url: string, options?: RequestInit): Promise<Response> {
  const now = Date.now();
  const wait = RATE_LIMIT_MS - (now - lastRequestTime);
  if (wait > 0) await new Promise(r => setTimeout(r, wait));
  lastRequestTime = Date.now();

  return fetch(url, {
    ...options,
    headers: {
      'User-Agent': 'PDC-Site/2.0',
      ...(options?.headers ?? {}),
    },
  });
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Get card by exact English name.
 */
export async function getCardByName(cardName: string): Promise<ScryfallCard | null> {
  const key = cacheKey(cardName);
  const cached = readCache(`name_${key}`);
  if (cached) return cached;

  const url = `${API_BASE}/cards/named?exact=${encodeURIComponent(cardName)}`;
  try {
    const res = await rateLimitedFetch(url);
    if (!res.ok) return null;
    const data = (await res.json()) as ScryfallCard;
    if (data.object === 'error') return null;
    writeCache(`name_${key}`, data);
    return data;
  } catch {
    return null;
  }
}

/**
 * Get card by set code and collector number.
 */
export async function getCardBySet(setCode: string, collectorNumber: string): Promise<ScryfallCard | null> {
  const key = `${setCode}_${collectorNumber}`;
  const cached = readCache(key);
  if (cached) return cached;

  const url = `${API_BASE}/cards/${encodeURIComponent(setCode)}/${encodeURIComponent(collectorNumber)}`;
  try {
    const res = await rateLimitedFetch(url);
    if (!res.ok) return null;
    const data = (await res.json()) as ScryfallCard;
    if (data.object === 'error') return null;
    writeCache(key, data);
    return data;
  } catch {
    return null;
  }
}

/**
 * Batch-fetch cards by name using /cards/collection (max 75 per request).
 * Returns a Map of lowercase(name) → ScryfallCard | null.
 */
export async function getCardsByNames(names: string[]): Promise<Map<string, ScryfallCard | null>> {
  const result = new Map<string, ScryfallCard | null>();
  const toFetch: string[] = [];

  // Check cache first
  for (const name of names) {
    const key = cacheKey(name);
    const cached = readCache(`name_${key}`);
    if (cached) {
      result.set(name.toLowerCase(), cached);
    } else {
      toFetch.push(name);
    }
  }

  if (toFetch.length === 0) return result;

  // Batch in chunks of 75
  for (let i = 0; i < toFetch.length; i += 75) {
    const batch = toFetch.slice(i, i + 75);
    const identifiers = batch.map(name => ({ name }));

    try {
      const res = await rateLimitedFetch(`${API_BASE}/cards/collection`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifiers }),
      });

      if (!res.ok) {
        for (const name of batch) result.set(name.toLowerCase(), null);
        continue;
      }

      const data = (await res.json()) as { data: ScryfallCard[] };
      const found = new Map<string, ScryfallCard>();

      for (const card of data.data ?? []) {
        const key = cacheKey(card.name);
        writeCache(`name_${key}`, card);
        found.set(card.name.toLowerCase(), card);
      }

      // Map requested names to results; fallback search for misses
      for (const name of batch) {
        const lower = name.toLowerCase();
        if (found.has(lower)) {
          result.set(lower, found.get(lower)!);
        } else {
          // Try matching DFC: "Garland, Knight of Cornelia" matches "Garland, Knight of Cornelia // Chaos"
          let matched: ScryfallCard | null = null;
          for (const [foundName, card] of found) {
            if (foundName.startsWith(lower + ' // ') || foundName.startsWith(lower + ' /')) {
              matched = card;
              // Also cache under the requested name for future lookups
              writeCache(`name_${cacheKey(name)}`, card);
              break;
            }
          }
          if (matched) {
            result.set(lower, matched);
          } else {
            const card = await searchCardByName(name);
            if (card) writeCache(`name_${cacheKey(name)}`, card);
            result.set(lower, card);
          }
        }
      }
    } catch {
      for (const name of batch) result.set(name.toLowerCase(), null);
    }
  }

  return result;
}

/**
 * Search for a card by name (supports non-English names).
 */
export async function searchCardByName(name: string): Promise<ScryfallCard | null> {
  const key = cacheKey(name);
  const cached = readCache(`name_${key}`);
  if (cached) return cached;

  // Try exact match first
  const exactUrl = `${API_BASE}/cards/named?exact=${encodeURIComponent(name)}`;
  try {
    const res = await rateLimitedFetch(exactUrl);
    if (res.ok) {
      const data = (await res.json()) as ScryfallCard;
      if (data.object === 'card') {
        writeCache(`name_${key}`, data);
        return data;
      }
    }
  } catch { /* fall through to search */ }

  // Fallback: search across languages
  const searchUrl = `${API_BASE}/cards/search?q=${encodeURIComponent(`!"${name}" include:extras`)}&unique=cards`;
  try {
    const res = await rateLimitedFetch(searchUrl);
    if (!res.ok) return null;
    const data = (await res.json()) as { data: ScryfallCard[] };
    const card = data.data?.[0] ?? null;
    if (card) writeCache(`name_${key}`, card);
    return card;
  } catch {
    return null;
  }
}

// ---------------------------------------------------------------------------
// Pure extractors (no API calls, no cache)
// ---------------------------------------------------------------------------

export function getCardImage(card: ScryfallCard | null, size: ImageSize = 'normal'): string | null {
  if (!card) return null;
  return card.image_uris?.[size]
    ?? card.card_faces?.[0]?.image_uris?.[size]
    ?? null;
}

export function getManaCost(card: ScryfallCard | null): string | null {
  if (!card) return null;
  return card.mana_cost
    ?? card.card_faces?.[0]?.mana_cost
    ?? null;
}

export function getCmc(card: ScryfallCard | null): number {
  return card?.cmc ?? 0;
}

export function getColors(card: ScryfallCard | null): string[] {
  if (!card) return [];
  return card.colors
    ?? card.card_faces?.[0]?.colors
    ?? [];
}

export function getTypeLine(card: ScryfallCard | null): string | null {
  if (!card) return null;
  return card.type_line
    ?? card.card_faces?.[0]?.type_line
    ?? null;
}

const TYPE_PRIORITY = ['Land', 'Creature', 'Planeswalker', 'Instant', 'Sorcery', 'Artifact', 'Enchantment'] as const;

export function getPrimaryType(card: ScryfallCard | null): string {
  const typeLine = getTypeLine(card);
  if (!typeLine) return 'Other';
  for (const type of TYPE_PRIORITY) {
    if (typeLine.toLowerCase().includes(type.toLowerCase())) return type;
  }
  return 'Other';
}
