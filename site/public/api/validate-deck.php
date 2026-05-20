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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'data'    => null,
        'error'   => 'Method not allowed. Use POST.',
    ));
    exit;
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
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'data'    => null,
        'error'   => 'Decklist too large.',
    ));
    exit;
}

// ---------------------------------------------------------------------------
// Validate
// ---------------------------------------------------------------------------

$result = DeckValidator::validate($commander_name, $partner_name, $decklist_text);

// ---------------------------------------------------------------------------
// Response
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'success' => true,
    'data'    => $result,
), JSON_UNESCAPED_UNICODE);
