<?php
/**
 * Decklist submission endpoint.
 *
 * POST commander, partner (optional), decklist, author (optional),
 * archetype (optional). Validates legality (DeckValidator) and opens a PR adding
 * the decklist JSON. Abuse guards (Turnstile, honeypot, rate limit) land in slice 3.
 *
 * Thin wrapper: the logic lives in DecklistSubmissionController.
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

// Read form-encoded or JSON body.
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (stripos($content_type, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = array();
    }
} else {
    $input = $_POST;
}

// Coarse size cap (a decklist submission is a few KB at most).
if (strlen(json_encode($input)) > 60000) {
    submit_fail(413, 'Soumission trop volumineuse.');
}

$token           = pdc_secret('GITHUB_TOKEN');
$turnstileSecret = pdc_secret('TURNSTILE_SECRET');
if (!$token || !$turnstileSecret) {
    // Secrets not placed on the server yet — see docs/external.
    error_log('PDC submit-decklist: missing secret(s)');
    submit_fail(503, 'Les soumissions sont temporairement indisponibles.');
}

$controller = new DecklistSubmissionController(
    new TurnstileVerifier($turnstileSecret),
    new GitHubClient($token, PDC_GITHUB_REPO)
);

try {
    $result = $controller->handle($input, RateLimiter::client_id());
} catch (RuntimeException $e) {
    // Ban list unavailable, or GitHub API error.
    error_log('PDC submit-decklist: ' . $e->getMessage());
    submit_fail(502, "La soumission n'a pas pu être créée. Reessayez plus tard.");
}

http_response_code($result['status']);
echo json_encode($result['body'], JSON_UNESCAPED_UNICODE);
