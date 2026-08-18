<?php
/**
 * Tournament submission endpoint (organizers).
 *
 * POST accessCode, title, date, location?, city?, participants?, top8[] and meta?
 * — as JSON, since top8 is nested. Opens a PR adding the tournament JSON plus one
 * decklist file per top-8 player who provided a legal list.
 *
 * Thin wrapper: the logic lives in TournamentSubmissionController.
 *
 * @package PDC_API
 */

require_once __DIR__ . '/lib/config.php';

pdc_set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function submit_fail($status, $message) {
    http_response_code($status);
    echo json_encode(array('success' => false, 'error' => $message), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    submit_fail(405, 'Method not allowed. Use POST.');
}

$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($content_type, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = array();
    }
} else {
    $input = $_POST;
}

// A tournament carries up to eight decklists, so the cap is eight times the
// decklist endpoint's rather than the same number.
if (strlen(json_encode($input)) > 500000) {
    submit_fail(413, 'Soumission trop volumineuse.');
}

// Validator messages come back per place, so they follow the organizer's language.
$locale = isset($input['locale']) && in_array($input['locale'], array('fr', 'en', 'it'), true)
    ? $input['locale']
    : 'fr';
DeckValidator::$locale = $locale;

$token           = pdc_secret('GITHUB_TOKEN');
$turnstileSecret = pdc_secret('TURNSTILE_SECRET');
$organizerCode   = pdc_secret('ORGANIZER_CODE');
if (!$token || !$turnstileSecret || !$organizerCode) {
    // Secrets not placed on the server yet — see docs/external.
    error_log('PDC submit-tournament: missing secret(s)');
    submit_fail(503, 'Les soumissions sont temporairement indisponibles.');
}

$controller = new TournamentSubmissionController(
    new TurnstileVerifier($turnstileSecret),
    new GitHubClient($token, PDC_GITHUB_REPO),
    $organizerCode
);

try {
    $result = $controller->handle($input, RateLimiter::client_id());
} catch (RuntimeException $e) {
    // Ban list unavailable, or GitHub API error.
    error_log('PDC submit-tournament: ' . $e->getMessage());
    submit_fail(502, "La soumission n'a pas pu être créée. Reessayez plus tard.");
}

http_response_code($result['status']);
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
