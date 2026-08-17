<?php
/**
 * Orchestrates a decklist submission — SLICE 2 (happy path).
 *
 * Guards, then builds the submission, checks the deck is legal (reusing
 * DeckValidator), and opens a PR. Returns a [status, body] pair and does NOT echo
 * — the entry script emits the response. Dependencies are injected so it is
 * testable without network.
 *
 * Guard order (cheapest first): honeypot -> rate limit -> Turnstile -> shape ->
 * legality -> PR. A request rejected by any guard never reaches the next.
 *
 * @package PDC_API
 * @since 2.2.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

final class DecklistSubmissionController {

    /** Hidden form field that must stay empty (honeypot). */
    const HONEYPOT_FIELD = 'website';
    /** Turnstile token field name (Cloudflare's default). */
    const TURNSTILE_FIELD = 'cf-turnstile-response';

    /** @var TurnstileVerifier */
    private $turnstile;
    /** @var GitHubClient */
    private $github;

    public function __construct(TurnstileVerifier $turnstile, GitHubClient $github) {
        $this->turnstile = $turnstile;
        $this->github    = $github;
    }

    /**
     * @param array  $input     Raw request fields
     * @param string $client_ip Client identifier for rate limiting
     * @return array{status:int, body:array}
     * @throws RuntimeException on a downstream failure (ban list / GitHub API)
     */
    public function handle(array $input, $client_ip) {
        // Honeypot: a filled hidden field means a bot. Reject silently-ish.
        $hp = isset($input[self::HONEYPOT_FIELD]) ? $input[self::HONEYPOT_FIELD] : '';
        if (is_string($hp) && trim($hp) !== '') {
            return $this->reply(400, array('error' => 'rejected'));
        }

        // Rate limit (submissions are expensive: open a PR).
        $rate = RateLimiter::consume($client_ip, PDC_SUBMIT_RATE_LIMIT, PDC_SUBMIT_RATE_WINDOW);
        if (empty($rate['allowed'])) {
            return $this->reply(429, array(
                'error'       => 'rate_limited',
                'retry_after' => isset($rate['retry_after']) ? $rate['retry_after'] : PDC_SUBMIT_RATE_WINDOW,
            ));
        }

        // Turnstile (fail-closed).
        $token = isset($input[self::TURNSTILE_FIELD]) ? $input[self::TURNSTILE_FIELD] : '';
        if (!$this->turnstile->verify(is_string($token) ? $token : '', $client_ip)) {
            return $this->reply(403, array('error' => 'captcha'));
        }

        // Shape.
        try {
            $submission = DecklistSubmission::from_input($input);
        } catch (InvalidArgumentException $e) {
            return $this->reply(422, array('error' => 'form', 'message' => $e->getMessage()));
        }

        // Legality — a garbage or illegal deck never becomes a PR.
        $validation = DeckValidator::validate(
            $submission->commander(),
            (string) $submission->partner(),
            $submission->decklist_text()
        );
        if (empty($validation['is_valid'])) {
            return $this->reply(422, array('error' => 'invalid', 'data' => $validation));
        }

        // Open the PR.
        $slug   = $submission->slug();
        $branch = 'submission/decklist-' . $slug;
        $files  = array($submission->repo_path() => $submission->to_json());

        $base = $this->github->base_sha('main');
        $this->github->commit_files($branch, $base, $files, 'Soumission decklist : ' . $submission->commander());
        $pr = $this->github->open_pull_request(
            $branch,
            'main',
            'Soumission decklist : ' . $submission->title(),
            $this->pr_body($submission, $validation)
        );

        return $this->reply(200, array('success' => true, 'pr_url' => $pr['html_url']));
    }

    private function pr_body(DecklistSubmission $s, array $validation) {
        $stats = isset($validation['stats']) ? $validation['stats'] : array();
        $total = isset($stats['total_cards']) ? $stats['total_cards'] : '?';
        $lines = array(
            'Soumission automatique via le formulaire du site.',
            '',
            '- **Général** : ' . $s->commander(),
        );
        if ($s->partner() !== null) {
            $lines[] = '- **Partenaire** : ' . $s->partner();
        }
        $lines[] = '- **Cartes** : ' . $total;
        $lines[] = '- **Validateur** : deck légal ✓';
        $lines[] = '';
        $lines[] = 'À relire avant merge.';
        return implode("\n", $lines);
    }

    private function reply($status, array $body) {
        if (!isset($body['success'])) {
            $body = array_merge(array('success' => false), $body);
        }
        return array('status' => $status, 'body' => $body);
    }
}
