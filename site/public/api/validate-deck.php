<?php
/**
 * PDC Deck Validator API Endpoint (standalone, no WordPress)
 *
 * POST /api/validate-deck.php
 * Content-Type: application/x-www-form-urlencoded
 *
 * Parameters:
 *   commander  (required)  Commander card name
 *   partner    (optional)  Partner card name
 *   decklist   (required)  MTGO-format decklist text
 *
 * Response (JSON):
 *   {
 *     "success": true,
 *     "data": {
 *       "is_valid": true|false,
 *       "errors": [{"rule": "...", "message": "...", "cards": ["..."]}],
 *       "warnings": ["..."],
 *       "stats": {"total_cards": 99, "unique_cards": 75}
 *     }
 *   }
 *
 * @package PDC_API
 * @since 2.0.0
 */

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

require_once __DIR__ . '/lib/config.php';

// CORS
pdc_set_cors_headers();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Emit a JSON error and stop.
 *
 * @param int    $status  HTTP status code
 * @param string $message Human-readable message (shown to the user)
 * @param array  $headers Extra headers to send
 */
function pdc_fail($status, $message, array $headers = array()) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $header) {
        header($header);
    }
    echo json_encode(array(
        'success' => false,
        'data'    => null,
        'error'   => $message,
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pdc_fail(405, 'Method not allowed. Use POST.');
}

// ---------------------------------------------------------------------------
// Rate limit
// ---------------------------------------------------------------------------

$rate = RateLimiter::consume(RateLimiter::client_id(), PDC_RATE_LIMIT, PDC_RATE_WINDOW);
header('X-RateLimit-Limit: ' . PDC_RATE_LIMIT);
header('X-RateLimit-Remaining: ' . $rate['remaining']);

if (!$rate['allowed']) {
    pdc_fail(
        429,
        'Trop de validations en peu de temps. Reessayez dans ' . $rate['retry_after'] . ' seconde(s).',
        array('Retry-After: ' . $rate['retry_after'])
    );
}

// ---------------------------------------------------------------------------
// Read & sanitize input
// ---------------------------------------------------------------------------

// Support both form-encoded and JSON body
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if (stripos($content_type, 'application/json') !== false) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = array();
    }
} else {
    // application/x-www-form-urlencoded (default)
    $body = $_POST;
}

$commander_name = isset($body['commander']) ? trim($body['commander']) : '';
$partner_name   = isset($body['partner'])   ? trim($body['partner'])   : '';
$decklist_text  = isset($body['decklist'])  ? $body['decklist']        : '';

// Basic input sanitization (strip tags, limit length)
$commander_name = strip_tags($commander_name);
$partner_name   = strip_tags($partner_name);
$decklist_text  = strip_tags($decklist_text);

// Hard limit to prevent abuse (a 100-card decklist is ~3 KB at most)
if (strlen($decklist_text) > 50000) {
    pdc_fail(413, 'Decklist too large.');
}

// Cap distinct card names: each unknown name can cost a Scryfall lookup, so the
// byte limit above is not enough on its own (50 KB of short junk names is
// thousands of them). A legal PDC deck has at most 100 distinct cards.
$unique_names = count(array_unique(array_column(DecklistParser::parse($decklist_text), 'name')));
if ($unique_names > PDC_MAX_UNIQUE_CARDS) {
    pdc_fail(
        422,
        'La decklist contient trop de cartes differentes (' . $unique_names
            . '). Un deck PDC en compte au plus 100.'
    );
}

// ---------------------------------------------------------------------------
// Validate
// ---------------------------------------------------------------------------

try {
    $result = DeckValidator::validate($commander_name, $partner_name, $decklist_text);
} catch (RuntimeException $e) {
    // The validator could not run a rule it is not allowed to skip (e.g. the ban
    // list is missing). Fail loudly rather than return a deck we did not fully check.
    error_log('PDC validate-deck: ' . $e->getMessage());
    pdc_fail(503, 'Le validateur est temporairement indisponible. Reessayez dans quelques instants.');
}

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'success' => true,
    'data'    => $result,
), JSON_UNESCAPED_UNICODE);
