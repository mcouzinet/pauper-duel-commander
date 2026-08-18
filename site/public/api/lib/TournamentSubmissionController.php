<?php
/**
 * Orchestrates a tournament submission — SLICE 6.
 *
 * Same shape as DecklistSubmissionController, with two differences that the
 * tournament case forces:
 *
 *  1. An organizer access code, checked AFTER Turnstile. Checking it first would
 *     be cheaper, but it would also let a bot brute-force the code without ever
 *     solving a captcha; the code is the only thing standing between the public
 *     and the results of a tournament.
 *  2. A multi-file commit: the tournament plus one decklist per top-8 player who
 *     provided one, `decklistSlug` wired between them, in a single commit.
 *
 * An illegal decklist does NOT sink the whole submission. A tournament that
 * happened is a fact, and `decklistSlug: null` is the collection's normal state
 * for a place with no list — so a rejected deck is simply left out and reported
 * back (response + PR body) instead of costing the organizer the other seven.
 * What stays invariant is the one that matters: an illegal deck never becomes
 * published content.
 *
 * Returns a [status, body] pair and does NOT echo — the entry script emits.
 *
 * @package PDC_API
 * @since 2.3.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

final class TournamentSubmissionController {

    /** Hidden form field that must stay empty (honeypot). */
    const HONEYPOT_FIELD = 'website';
    /** Turnstile token field name (Cloudflare's default). */
    const TURNSTILE_FIELD = 'cf-turnstile-response';

    /** @var TurnstileVerifier */
    private $turnstile;
    /** @var GitHubClient */
    private $github;
    /** @var string */
    private $organizer_code;
    /** @var callable():float monotonic-ish clock, injectable so tests can force the deadline */
    private $clock;

    public function __construct(TurnstileVerifier $turnstile, GitHubClient $github, $organizer_code, ?callable $clock = null) {
        $this->turnstile      = $turnstile;
        $this->github         = $github;
        $this->organizer_code = (string) $organizer_code;
        $this->clock          = $clock ? $clock : function () { return microtime(true); };
    }

    /**
     * @param array  $input     Raw request fields
     * @param string $client_ip Client identifier for rate limiting
     * @return array{status:int, body:array}
     * @throws RuntimeException on a downstream failure (ban list / GitHub API)
     */
    public function handle(array $input, $client_ip) {
        $hp = isset($input[self::HONEYPOT_FIELD]) ? $input[self::HONEYPOT_FIELD] : '';
        if (is_string($hp) && trim($hp) !== '') {
            return $this->reply(400, array('error' => 'rejected'));
        }

        $rate = RateLimiter::consume($client_ip, PDC_SUBMIT_RATE_LIMIT, PDC_SUBMIT_RATE_WINDOW);
        if (empty($rate['allowed'])) {
            return $this->reply(429, array(
                'error'       => 'rate_limited',
                'retry_after' => isset($rate['retry_after']) ? $rate['retry_after'] : PDC_SUBMIT_RATE_WINDOW,
            ));
        }

        $token = isset($input[self::TURNSTILE_FIELD]) ? $input[self::TURNSTILE_FIELD] : '';
        if (!$this->turnstile->verify(is_string($token) ? $token : '', $client_ip)) {
            return $this->reply(403, array('error' => 'captcha'));
        }

        // Organizer gate. hash_equals keeps the comparison constant-time so the
        // code cannot be recovered one character at a time.
        $submitted = isset($input['accessCode']) && is_string($input['accessCode']) ? trim($input['accessCode']) : '';
        if ($this->organizer_code === '' || !hash_equals($this->organizer_code, $submitted)) {
            return $this->reply(403, array('error' => 'access_code'));
        }

        try {
            $submission = TournamentSubmission::from_input($input);
        } catch (InvalidArgumentException $e) {
            // A stable code, resolved into the organizer's language by the form.
            return $this->reply(422, array('error' => 'form', 'reason' => $e->getMessage()));
        }

        // Legality pass over the submitted decklists, under a wall-clock budget.
        // Each validation is a Scryfall round trip plus up to PDC_MAX_FALLBACK_LOOKUPS
        // individual lookups gated at 100 ms; eight of those in one request can
        // outlast max_execution_time, and a timeout would give the organizer a
        // blank 500 after they typed in eight decklists.
        $deadline = $this->clock->__invoke() + PDC_SUBMIT_VALIDATION_BUDGET;
        $files    = array();
        $slugs    = array();
        $included = array();
        $rejected = array();

        foreach ($submission->entries() as $entry) {
            if ($entry['decklist'] === null) {
                continue;
            }
            $place = $entry['place'];

            if ($this->clock->__invoke() >= $deadline) {
                $rejected[] = array(
                    'place'      => $place,
                    'playerName' => $entry['playerName'],
                    'reason'     => 'not_checked',
                    'errors'     => array(),
                );
                continue;
            }

            try {
                $decklist = $submission->decklist_for($place);
            } catch (InvalidArgumentException $e) {
                continue; // no decklist for that place after all
            }

            $validation = DeckValidator::validate(
                $decklist->commander(),
                (string) $decklist->partner(),
                $decklist->decklist_text()
            );

            if (empty($validation['is_valid'])) {
                $rejected[] = array(
                    'place'      => $place,
                    'playerName' => $entry['playerName'],
                    'reason'     => 'invalid',
                    'errors'     => isset($validation['errors']) ? $validation['errors'] : array(),
                );
                continue;
            }

            $files[$decklist->repo_path()] = $decklist->to_json();
            $slugs[$place]                 = $decklist->slug();
            $included[]                    = $place;
        }

        // The tournament file is written last so its decklistSlug values match
        // exactly the decklists that made it into this same commit.
        $files[$submission->repo_path()] = $submission->to_json($slugs);

        $branch = 'submission/tournament-' . $submission->slug();
        $base   = $this->github->base_sha('main');
        $this->github->commit_files($branch, $base, $files, 'Soumission tournoi : ' . $submission->title());
        $pr = $this->github->open_pull_request(
            $branch,
            'main',
            'Soumission tournoi : ' . $submission->title(),
            $this->pr_body($submission, $included, $rejected),
            array(PDC_LABEL_TOURNAMENT)
        );

        return $this->reply(200, array(
            'success'  => true,
            'pr_url'   => $pr['html_url'],
            'included' => $included,
            'rejected' => $rejected,
        ));
    }

    private function pr_body(TournamentSubmission $s, array $included, array $rejected) {
        $lines = array(
            'Soumission automatique via le formulaire organisateur.',
            '',
            '- **Tournoi** : ' . $s->title(),
            '- **Date** : ' . $s->date(),
            '- **Participants** : ' . ($s->participants() > 0 ? $s->participants() : 'non renseigne'),
            '- **Top 8** : ' . count($s->entries()) . ' place(s)',
            '- **Meta** : ' . count($s->meta_list()) . ' general(aux)',
            '- **Decklists jointes** : ' . count($included) . '/' . count($s->places_with_decklist()),
            '',
        );

        if ($rejected) {
            $lines[] = '### Decklists ecartees';
            $lines[] = '';
            $lines[] = 'Ces listes ne sont PAS dans la PR ; leur place garde `decklistSlug: null`.';
            $lines[] = '';
            foreach ($rejected as $r) {
                $why = $r['reason'] === 'not_checked'
                    ? 'non verifiee (budget de validation epuise)'
                    : 'refusee par le validateur';
                $lines[] = '- **Place ' . $r['place'] . '** (' . $r['playerName'] . ') — ' . $why;
                foreach ($r['errors'] as $err) {
                    $msg = isset($err['message']) ? $err['message'] : (isset($err['rule']) ? $err['rule'] : '?');
                    $lines[] = '  - ' . $msg;
                }
            }
            $lines[] = '';
        }

        $lines[] = '### A relire avant merge';
        $lines[] = '';
        // The names in top8/metaList are not checked against Scryfall here — that
        // would be another lookup per name. They must be canonical: the metagame
        // panel and the card images key off them.
        $lines[] = '- Orthographe Scryfall des generaux (`top8`, `metaList`) — non verifiee automatiquement.';
        $lines[] = '- Le fichier `' . $s->repo_path() . '` : "added" attendu. "Modified" = un tournoi existant porte deja ce slug.';

        return implode("\n", $lines);
    }

    private function reply($status, array $body) {
        if (!isset($body['success'])) {
            $body = array_merge(array('success' => false), $body);
        }
        return array('status' => $status, 'body' => $body);
    }
}
