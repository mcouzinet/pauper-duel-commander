/**
 * Copies the ban list into the API directory.
 *
 * `content/banlist.json` is the source of truth (it feeds the Astro pages at
 * build time). The PHP validator needs the same data at runtime, and it can only
 * rely on paths that survive the deploy — `public/api/` is rsynced to the VPS as
 * a unit, `content/` is not. So the ban list is copied into `public/api/data/`,
 * which resolves identically in the repo, in `dist/`, and in production.
 *
 * Run automatically before `dev` and `build`.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'content', 'banlist.json');
const target = join(root, 'public', 'api', 'data', 'banlist.json');

const raw = readFileSync(source, 'utf8');

// Fail the build rather than ship a ban list the validator will reject at runtime.
const parsed = JSON.parse(raw);
if (!Array.isArray(parsed.cards) || parsed.cards.length === 0) {
  throw new Error(`${source}: expected a non-empty "cards" array`);
}

mkdirSync(dirname(target), { recursive: true });
writeFileSync(target, raw);

console.log(`[banlist] ${parsed.cards.length} banned cards → public/api/data/banlist.json`);
