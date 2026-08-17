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
// Abuse limits
// ---------------------------------------------------------------------------

/** Rate limit state directory */
if (!defined('PDC_RATELIMIT_DIR')) {
    define('PDC_RATELIMIT_DIR', __DIR__ . '/../cache/ratelimit');
}

/** Max validation requests per IP per window */
if (!defined('PDC_RATE_LIMIT')) {
    define('PDC_RATE_LIMIT', 20);
}

/** Rate limit window in seconds */
if (!defined('PDC_RATE_WINDOW')) {
    define('PDC_RATE_WINDOW', 60);
}

/**
 * Max distinct card names accepted in one decklist.
 *
 * A legal PDC deck has at most 100 distinct cards. The cap matters because every
 * name Scryfall does not return in the bulk call triggers an individual fallback
 * search, each gated by a 100 ms sleep — so an unbounded list of junk names is a
 * way to hold a PHP worker open and hammer Scryfall from a single request.
 */
if (!defined('PDC_MAX_UNIQUE_CARDS')) {
    define('PDC_MAX_UNIQUE_CARDS', 120);
}

/** Max individual Scryfall fallback lookups per request (see above) */
if (!defined('PDC_MAX_FALLBACK_LOOKUPS')) {
    define('PDC_MAX_FALLBACK_LOOKUPS', 20);
}

// ---------------------------------------------------------------------------
// Submissions (decklist / tournament forms -> GitHub PR)
// ---------------------------------------------------------------------------

/** Target repository, "owner/name", where submission PRs are opened. */
if (!defined('PDC_GITHUB_REPO')) {
    define('PDC_GITHUB_REPO', 'mcouzinet/pauper-duel-commander');
}

/** Submissions are far more expensive than a validation (they open a PR): tighter cap. */
if (!defined('PDC_SUBMIT_RATE_LIMIT')) {
    define('PDC_SUBMIT_RATE_LIMIT', 5);
}
if (!defined('PDC_SUBMIT_RATE_WINDOW')) {
    define('PDC_SUBMIT_RATE_WINDOW', 3600); // 1 hour
}

/**
 * Read a secret by name.
 *
 * Secrets never live in the deployed tree. In production they sit in a PHP file
 * OUTSIDE the web root, whose absolute path is given by the PDC_SECRETS_FILE env
 * var; that file returns an associative array. Falls back to a PDC_<NAME> env var
 * (handy for local dev / CI). Returns null if absent.
 *
 * @return string|null
 */
function pdc_secret($name) {
    static $store = null;
    if ($store === null) {
        $store = array();
        $file = getenv('PDC_SECRETS_FILE');
        // Default OVH location: pdc-secrets.php in the account home, i.e. the
        // directory that CONTAINS www/ (three levels above this file once
        // deployed: www/api/lib -> home). Outside the web root, never deployed.
        if (!$file || !is_readable($file)) {
            $fallback = __DIR__ . '/../../../pdc-secrets.php';
            if (is_readable($fallback)) {
                $file = $fallback;
            }
        }
        if ($file && is_readable($file)) {
            $loaded = include $file;
            if (is_array($loaded)) {
                $store = $loaded;
            }
        }
    }
    if (array_key_exists($name, $store)) {
        return $store[$name];
    }
    $env = getenv('PDC_' . $name);
    return $env === false ? null : $env;
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

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/ScryfallService.php';
require_once __DIR__ . '/DecklistParser.php';
require_once __DIR__ . '/DeckValidator.php';
require_once __DIR__ . '/GitHubClient.php';
require_once __DIR__ . '/TurnstileVerifier.php';
require_once __DIR__ . '/DecklistSubmission.php';
require_once __DIR__ . '/DecklistSubmissionController.php';
