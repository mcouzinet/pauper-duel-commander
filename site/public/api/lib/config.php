<?php
/**
 * PDC API Configuration
 *
 * Constants, autoloading, and shared helpers for the standalone PDC validator API.
 * Replaces WordPress-specific functions (transients, sanitize_key, etc.) with
 * pure PHP equivalents.
 *
 * @package PDC_API
 * @since 2.0.0
 */

// Prevent direct access (must be included, not loaded directly)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

// Constants are guarded so tests can point them at fixtures before including
// this file.

// ---------------------------------------------------------------------------
// Cache
// ---------------------------------------------------------------------------

/** Scryfall cache directory (api/cache/scryfall) */
if (!defined('PDC_CACHE_DIR')) {
    define('PDC_CACHE_DIR', __DIR__ . '/../cache/scryfall');
}

/** Cache TTL in seconds (30 days) */
if (!defined('PDC_CACHE_TTL')) {
    define('PDC_CACHE_TTL', 30 * 24 * 60 * 60);
}

// ---------------------------------------------------------------------------
// Scryfall
// ---------------------------------------------------------------------------

define('SCRYFALL_API_BASE', 'https://api.scryfall.com');
define('SCRYFALL_USER_AGENT', 'PDC-API/2.0; https://pauperdualcommander.com');
define('SCRYFALL_RATE_LIMIT_MS', 100);

// ---------------------------------------------------------------------------
// Ban list
// ---------------------------------------------------------------------------

/**
 * Resolve the ban list path.
 *
 * `api/data/banlist.json` is the canonical, deployable location: it sits inside
 * the directory that gets rsynced to the VPS, so it resolves identically in the
 * repo, in `dist/`, and in production. It is produced by `npm run build`
 * (scripts/copy-banlist.mjs) from the source of truth, `site/content/banlist.json`.
 *
 * The `content/` fallback keeps a checkout that has never been built working.
 *
 * @return string First candidate that exists, or the canonical path if none do
 *                (so the error message names the location we actually want).
 */
function pdc_resolve_banlist_path() {
    $candidates = array(
        __DIR__ . '/../data/banlist.json',        // deployed / built
        __DIR__ . '/../../../content/banlist.json', // un-built checkout
    );
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return $candidates[0];
}

if (!defined('PDC_BANLIST_PATH')) {
    define('PDC_BANLIST_PATH', pdc_resolve_banlist_path());
}

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------

/**
 * Set CORS headers.
 *
 * In production (PDC_DEV not set), restricts to same-origin.
 * In dev mode (PDC_DEV=1 env var), allows any origin.
 */
function pdc_set_cors_headers() {
    $dev = getenv('PDC_DEV') === '1';
    if ($dev) {
        header('Access-Control-Allow-Origin: *');
    }
    // Same-origin requests don't need an explicit Allow-Origin header;
    // the browser enforces same-origin by default.
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// ---------------------------------------------------------------------------
// Helpers (WordPress replacements)
// ---------------------------------------------------------------------------

/**
 * Sanitize a string for use as a cache key / filename.
 * Replaces WordPress sanitize_key().
 *
 * @param string $key Raw key
 * @return string Lowercase alphanumeric + dashes
 */
function pdc_sanitize_key($key) {
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9_\-]/', '-', $key);
    $key = preg_replace('/-+/', '-', $key);
    return trim($key, '-');
}

// ---------------------------------------------------------------------------
// Autoload classes
// ---------------------------------------------------------------------------

require_once __DIR__ . '/ScryfallService.php';
require_once __DIR__ . '/DecklistParser.php';
require_once __DIR__ . '/DeckValidator.php';
