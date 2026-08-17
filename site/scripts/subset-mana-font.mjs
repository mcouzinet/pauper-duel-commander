/**
 * Subsets the vendored mana font to the glyphs this site can actually render.
 *
 * The full font is 183 KB of Private Use Area glyphs covering every symbol Magic
 * has ever printed — planeswalker loyalty, tap variants, set icons, guild marks.
 * The site renders mana costs and colour identities, which is a few dozen of
 * them: `src/styles/mana.css` is the authority on which codepoint each `.ms-*`
 * class maps to, so the subset is derived from it rather than guessed.
 *
 * Run manually after bumping the vendored font:
 *   node scripts/subset-mana-font.mjs
 *
 * Needs Python's fonttools (`pip install fonttools brotli`). Skips with a clear
 * message when it is absent, so a build never depends on it.
 */
import { readFileSync, writeFileSync, existsSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const CSS = join(ROOT, 'src', 'styles', 'mana.css');
// Outside public/ so the 183 KB original is never deployed, and outside vendor/
// which .gitignore claims for Composer.
const FULL = join(ROOT, 'assets', 'mana', 'mana-1.18.0-full.woff2');
const OUT = join(ROOT, 'public', 'fonts', 'mana', 'mana-1.18.0.woff2');

/**
 * Every class the site can emit.
 *
 * `formatManaCost()` turns a Scryfall cost into `ms-<symbol>`, so the set is
 * bounded by what Scryfall prints: colours, generic costs, hybrids in both
 * orders, twobrid, Phyrexian (mono and hybrid), plus the handful of symbols that
 * appear inside rules text.
 */
const COLORS = ['w', 'u', 'b', 'r', 'g'];

const HYBRID_PAIRS = [];
for (const a of COLORS) {
  for (const b of COLORS) {
    if (a !== b) HYBRID_PAIRS.push(a + b);
  }
}

const USED_CLASSES = [
  ...COLORS.map(c => `ms-${c}`),
  'ms-c', 'ms-x', 'ms-y', 'ms-z', 'ms-p', 'ms-s', 'ms-e',
  'ms-tap', 'ms-untap', 'ms-chaos', 'ms-half', 'ms-infinity',
  ...Array.from({ length: 21 }, (_, i) => `ms-${i}`),
  ...HYBRID_PAIRS.map(pair => `ms-${pair}`),
  ...HYBRID_PAIRS.map(pair => `ms-${pair}p`),
  ...COLORS.map(c => `ms-2${c}`),
  ...COLORS.map(c => `ms-${c}p`),
];

const css = readFileSync(CSS, 'utf-8');

/**
 * Map each class to its glyph.
 *
 * The minified stylesheet groups selectors (`.ms-wu::before,.ms-uw::before{…}`)
 * and stores the Private Use Area character literally rather than as a `\e600`
 * escape, so scan every content rule once and index it by every class in its
 * selector list. A class the font does not define contributes nothing.
 */
const glyphByClass = new Map();
for (const rule of css.matchAll(/([^{}]+)\{\s*content:\s*"([^"]+)"/g)) {
  const [, selectors, value] = rule;
  const escape = /^\\([0-9a-f]+)$/i.exec(value.trim());
  const codepoint = escape ? parseInt(escape[1], 16) : value.codePointAt(0);
  if (codepoint === undefined) continue;

  for (const selector of selectors.split(',')) {
    const name = /\.(ms-[a-z0-9-]+)::?before\s*$/i.exec(selector.trim());
    if (name) glyphByClass.set(name[1].toLowerCase(), codepoint);
  }
}

const codepoints = new Set();
const missing = [];
for (const cls of USED_CLASSES) {
  const codepoint = glyphByClass.get(cls);
  if (codepoint === undefined) missing.push(cls);
  else codepoints.add(`U+${codepoint.toString(16).toUpperCase()}`);
}

if (codepoints.size === 0) {
  console.error('[mana] no codepoints found in mana.css — nothing to subset');
  process.exit(1);
}

if (!existsSync(FULL)) {
  console.error(`[mana] missing ${FULL} — the unsubsetted original is needed to re-subset.`);
  process.exit(1);
}

try {
  execFileSync('python3', ['-c', 'import fontTools, brotli'], { stdio: 'ignore' });
} catch {
  console.warn('[mana] fonttools/brotli not installed — keeping the existing font. `pip install fonttools brotli` to re-subset.');
  process.exit(0);
}

const before = statSync(FULL).size;

execFileSync('python3', [
  '-m', 'fontTools.subset', FULL,
  `--unicodes=${[...codepoints].join(',')}`,
  '--flavor=woff2',
  '--layout-features=',
  '--no-hinting',
  '--desubroutinize',
  `--output-file=${OUT}`,
], { stdio: 'inherit' });

const after = statSync(OUT).size;
console.log(
  `[mana] ${codepoints.size} glyphs kept · ${(before / 1024).toFixed(0)} KB → ${(after / 1024).toFixed(0)} KB` +
  ` (−${Math.round((1 - after / before) * 100)}%)`,
);
if (missing.length > 0) {
  // Expected: the font defines one canonical order per hybrid pair (ms-wu, not
  // ms-uw) and Scryfall emits costs in that order, so the mirrored spellings this
  // script asks for simply do not exist.
  console.log(`[mana] ${missing.length} class(es) not defined by the font (mirrored hybrids), skipped`);
}
