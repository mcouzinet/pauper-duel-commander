<?php
/**
 * Decklist submission endpoint — SLICE 1 (tracer bullet).
 *
 * For now it only proves the pipeline: POST -> open a GitHub PR adding a trivial
 * file. No input validation, no Turnstile, no rate limit yet — those arrive in
 * slices 2 and 3, when the real DecklistSubmission / Controller replace this body.
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

$token = pdc_secret('GITHUB_TOKEN');
if (!$token) {
    // Secrets not placed on the server yet — see docs/external.
    error_log('PDC submit-decklist: GITHUB_TOKEN missing');
    submit_fail(503, 'Les soumissions sont temporairement indisponibles.');
}

try {
    $github = new GitHubClient($token, PDC_GITHUB_REPO);

    $id     = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    $branch = 'submission/tracer-' . $id;
    $files  = array(
        "docs/submissions-test/{$id}.txt" =>
            "Tracer bullet: pipeline de soumission opérationnel.\nCréé le " . date('c') . "\n",
    );

    $base = $github->base_sha('main');
    $github->commit_files($branch, $base, $files, "Tracer bullet submission {$id}");
    $pr = $github->open_pull_request($branch, 'main', "[test] Tracer bullet {$id}", "PR de test générée par l'endpoint de soumission (slice 1).");

    echo json_encode(array('success' => true, 'pr_url' => $pr['html_url']), JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    error_log('PDC submit-decklist: ' . $e->getMessage());
    submit_fail(502, "La soumission n'a pas pu être créée. Reessayez plus tard.");
}
