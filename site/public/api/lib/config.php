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

// ---------------------------------------------------------------------------
// Cache
// ---------------------------------------------------------------------------

/** Cache root directory (one level above api/) */
define('PDC_CACHE_DIR', __DIR__ . '/../cache/scryfall');

/** Cache TTL in seconds (30 days) */
define('PDC_CACHE_TTL', 30 * 24 * 60 * 60);

// ---------------------------------------------------------------------------
// Scryfall
// ---------------------------------------------------------------------------

define('SCRYFALL_API_BASE', 'https://api.scryfall.com');
define('SCRYFALL_USER_AGENT', 'PDC-API/2.0; https://pauperdualcommander.com');
define('SCRYFALL_RATE_LIMIT_MS', 100);

// ---------------------------------------------------------------------------
// Ban list
// ---------------------------------------------------------------------------

/** Path to the exported ban list JSON */
define('PDC_BANLIST_PATH', __DIR__ . '/../../../content/banlist.json');

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
