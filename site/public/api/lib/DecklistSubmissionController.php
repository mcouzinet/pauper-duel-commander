<?php
/**
 * Orchestrates a decklist submission — SLICE 2 (happy path).
 *
 * Builds the submission, checks the deck is legal (reusing DeckValidator), and
 * opens a PR. Returns a [status, body] pair and does NOT echo — the entry script
 * emits the response. Dependencies are injected so it is testable without network.
 *
 * The abuse guards (Turnstile, honeypot, rate limit) arrive in slice 3.
 *
 * @package PDC_API
 * @since 2.2.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

final class DecklistSubmissionController {

    /** @var GitHubClient */
    private $github;

    public function __construct(GitHubClient $github) {
        $this->github = $github;
    }

    /**
     * @param array  $input     Raw request fields
     * @param string $client_ip For rate limiting (unused until slice 3)
     * @return array{status:int, body:array}
     * @throws RuntimeException on a downstream failure (ban list / GitHub API)
     */
    public function handle(array $input, $client_ip) {
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
