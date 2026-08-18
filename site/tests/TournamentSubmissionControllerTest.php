<?php

use PHPUnit\Framework\TestCase;

/**
 * The controller wires the guards (honeypot, rate limit, Turnstile, organizer
 * code) then TournamentSubmission + DeckValidator + GitHubClient, and commits the
 * tournament together with the legal decklists in one call.
 *
 * All network is stubbed; DeckValidator reads through the fixture-seeded Scryfall
 * cache and the real ban list. Each test uses a unique client id so the
 * file-backed rate limiter cannot bleed between tests.
 */
class TournamentSubmissionControllerTest extends TestCase
{
    const CODE = 'organizer-code';

    private $calls;
    private $responses;

    protected function setUp(): void
    {
        $this->calls = array();
        $this->responses = array();
    }

    private function ip(string $suffix = ''): string
    {
        return '198.51.100.' . crc32($this->getName() . $suffix) % 250;
    }

    private function github(): GitHubClient
    {
        $calls =& $this->calls;
        $responses =& $this->responses;
        $http = function ($method, $url, $json) use (&$calls, &$responses) {
            $calls[] = array('method' => $method, 'url' => $url, 'json' => $json);
            return array_shift($responses) ?: array('status' => 500, 'body' => array());
        };
        return new GitHubClient('tok', 'owner/repo', $http);
    }

    private function turnstile(bool $ok): TurnstileVerifier
    {
        $http = function ($url, $fields) use ($ok) { return array('success' => $ok); };
        return new TurnstileVerifier('secret', $http);
    }

    private function controller(bool $turnstileOk = true, ?callable $clock = null): TournamentSubmissionController
    {
        return new TournamentSubmissionController(
            $this->turnstile($turnstileOk), $this->github(), self::CODE, $clock
        );
    }

    private function queueFullPrFlow(): void
    {
        $this->responses = array(
            array('status' => 200, 'body' => array('object' => array('sha' => 'base'))),
            array('status' => 200, 'body' => array('tree' => array('sha' => 'bt'))),
            array('status' => 201, 'body' => array('sha' => 'nt')),
            array('status' => 201, 'body' => array('sha' => 'nc')),
            array('status' => 201, 'body' => array('ref' => 'refs/heads/x')),
            array('status' => 201, 'body' => array('number' => 12, 'html_url' => 'https://github.com/owner/repo/pull/12')),
        );
    }

    /** @return array<string,string> committed path => content */
    private function committedFiles(): array
    {
        foreach ($this->calls as $c) {
            if (substr($c['url'], -strlen('/git/trees')) !== '/git/trees') {
                continue;
            }
            $files = array();
            foreach ($c['json']['tree'] as $item) {
                $files[$item['path']] = $item['content'];
            }
            return $files;
        }
        return array();
    }

    private function submission(array $over = array()): array
    {
        return array_merge(array(
            'cf-turnstile-response' => 'tok',
            'accessCode'            => self::CODE,
            'title'                 => 'Artefact #7',
            'date'                  => '2026-05-25',
            'location'              => 'Artefact',
            'city'                  => 'Bordeaux',
            'participants'          => 18,
            'top8'                  => array(),
            'meta'                  => '',
        ), $over);
    }

    /** A legal deck built from the Scryfall fixtures. */
    private function legalRow(int $place, string $player = 'Axel'): array
    {
        return array(
            'place' => $place, 'playerName' => $player,
            'commanderName' => 'Mother of Runes', 'score' => '4-0',
            'decklist' => '99 Plains',
        );
    }

    // -- happy path ----------------------------------------------------------

    public function testHappyPathCommitsTournamentAndDecklistTogether(): void
    {
        $this->queueFullPrFlow();

        $res = $this->controller()->handle(
            $this->submission(array('top8' => array($this->legalRow(1)))),
            $this->ip()
        );

        $this->assertSame(200, $res['status']);
        $this->assertSame('https://github.com/owner/repo/pull/12', $res['body']['pr_url']);
        $this->assertSame(array(1), $res['body']['included']);
        $this->assertSame(array(), $res['body']['rejected']);

        // One commit, both files.
        $files = $this->committedFiles();
        $this->assertCount(2, $files);
        $this->assertArrayHasKey('site/content/tournaments/artefact-7.json', $files);
        $this->assertArrayHasKey('site/content/decklists/mother-of-runes-artefact-7-1er.json', $files);

        // ...and the tournament points at the decklist committed alongside it.
        $tournament = json_decode($files['site/content/tournaments/artefact-7.json'], true);
        $this->assertSame('mother-of-runes-artefact-7-1er', $tournament['top8'][0]['decklistSlug']);
    }

    public function testTournamentWithNoDecklistsStillOpensAPr(): void
    {
        $this->queueFullPrFlow();

        $res = $this->controller()->handle(
            $this->submission(array('top8' => array(
                array('place' => 1, 'playerName' => 'Axel', 'commanderName' => 'Baleful Strix', 'score' => '4-0'),
            ))),
            $this->ip()
        );

        $this->assertSame(200, $res['status']);
        $files = $this->committedFiles();
        $this->assertSame(array('site/content/tournaments/artefact-7.json'), array_keys($files));
        $this->assertNull(json_decode($files['site/content/tournaments/artefact-7.json'], true)['top8'][0]['decklistSlug']);
    }

    // -- guards --------------------------------------------------------------

    public function testWrongAccessCodeReturns403AndOpensNoPr(): void
    {
        $res = $this->controller()->handle(
            $this->submission(array('accessCode' => 'not-the-code')),
            $this->ip()
        );

        $this->assertSame(403, $res['status']);
        $this->assertSame('access_code', $res['body']['error']);
        $this->assertSame(array(), $this->calls, 'GitHub must not be called');
    }

    public function testMissingAccessCodeReturns403(): void
    {
        $input = $this->submission();
        unset($input['accessCode']);

        $res = $this->controller()->handle($input, $this->ip());

        $this->assertSame(403, $res['status']);
        $this->assertSame('access_code', $res['body']['error']);
    }

    public function testServerWithNoConfiguredCodeAcceptsNobody(): void
    {
        $ctrl = new TournamentSubmissionController($this->turnstile(true), $this->github(), '');

        $res = $ctrl->handle($this->submission(array('accessCode' => '')), $this->ip());

        $this->assertSame(403, $res['status']);
        $this->assertSame(array(), $this->calls);
    }

    public function testBadTurnstileReturns403BeforeTheAccessCodeIsEvenConsidered(): void
    {
        // A valid code with a failed captcha still stops at the captcha, so the
        // code cannot be brute-forced without solving one each time.
        $res = $this->controller(false)->handle($this->submission(), $this->ip());

        $this->assertSame(403, $res['status']);
        $this->assertSame('captcha', $res['body']['error']);
        $this->assertSame(array(), $this->calls);
    }

    public function testHoneypotFilledIsRejectedBeforeAnything(): void
    {
        $res = $this->controller()->handle(
            $this->submission(array('website' => 'spam')),
            $this->ip()
        );

        $this->assertSame(400, $res['status']);
        $this->assertSame(array(), $this->calls);
    }

    public function testRateLimitedAfterQuota(): void
    {
        $ip = $this->ip();
        $ctrl = $this->controller();
        // PDC_SUBMIT_RATE_LIMIT = 5. A bad access code stops each allowed call
        // early, so no GitHub responses are needed.
        $bad = $this->submission(array('accessCode' => 'wrong'));
        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame(403, $ctrl->handle($bad, $ip)['status'], "call $i should pass the rate limit");
        }
        $sixth = $ctrl->handle($bad, $ip);
        $this->assertSame(429, $sixth['status']);
        $this->assertArrayHasKey('retry_after', $sixth['body']);
    }

    public function testBadFormReturns422AndOpensNoPr(): void
    {
        $res = $this->controller()->handle(
            $this->submission(array('date' => 'hier')),
            $this->ip()
        );

        $this->assertSame(422, $res['status']);
        $this->assertSame('form', $res['body']['error']);
        // A code the form localizes, not a French sentence sent to every locale.
        $this->assertSame('date_required', $res['body']['reason']);
        $this->assertSame(array(), $this->calls);
    }

    public function testFutureDatedResultsAreRefusedWithoutAPr(): void
    {
        $res = $this->controller()->handle(
            $this->submission(array('date' => gmdate('Y-m-d', time() + 10 * 86400))),
            $this->ip()
        );

        $this->assertSame(422, $res['status']);
        $this->assertSame('date_future', $res['body']['reason']);
        $this->assertSame(array(), $this->calls);
    }

    // -- decklist legality ---------------------------------------------------

    public function testIllegalDecklistIsLeftOutButTheTournamentStillLands(): void
    {
        $this->queueFullPrFlow();

        $res = $this->controller()->handle(
            $this->submission(array('top8' => array(
                $this->legalRow(1, 'Axel'),
                array(
                    'place' => 2, 'playerName' => 'Guislain',
                    'commanderName' => 'Mother of Runes', 'score' => '3-1',
                    'decklist' => "98 Plains\n1 Goliath Paladin", // banned card
                ),
            ))),
            $this->ip()
        );

        $this->assertSame(200, $res['status']);
        $this->assertSame(array(1), $res['body']['included']);
        $this->assertCount(1, $res['body']['rejected']);
        $this->assertSame(2, $res['body']['rejected'][0]['place']);
        $this->assertSame('invalid', $res['body']['rejected'][0]['reason']);
        $this->assertNotEmpty($res['body']['rejected'][0]['errors']);

        $files = $this->committedFiles();
        $this->assertCount(2, $files, 'the banned deck must not become a file');
        $this->assertArrayNotHasKey('site/content/decklists/mother-of-runes-artefact-7-2eme.json', $files);

        $top8 = json_decode($files['site/content/tournaments/artefact-7.json'], true)['top8'];
        $this->assertSame('mother-of-runes-artefact-7-1er', $top8[0]['decklistSlug']);
        $this->assertNull($top8[1]['decklistSlug'], 'the rejected place keeps its result, without a list');
    }

    public function testDecklistsPastTheTimeBudgetAreReportedUnchecked(): void
    {
        $this->queueFullPrFlow();

        // deadline = 0 + BUDGET; place 1 is checked, then the clock jumps past it.
        $ticks = array(0.0, 0.0, PDC_SUBMIT_VALIDATION_BUDGET + 1);
        $clock = function () use (&$ticks) {
            return count($ticks) > 1 ? array_shift($ticks) : $ticks[0];
        };

        $res = $this->controller(true, $clock)->handle(
            $this->submission(array('top8' => array(
                $this->legalRow(1, 'Axel'),
                $this->legalRow(2, 'Guislain'),
            ))),
            $this->ip()
        );

        $this->assertSame(200, $res['status']);
        $this->assertSame(array(1), $res['body']['included']);
        $this->assertSame('not_checked', $res['body']['rejected'][0]['reason']);
        $this->assertSame(2, $res['body']['rejected'][0]['place']);

        // Unchecked means left out, exactly like rejected: never published unverified.
        $this->assertArrayNotHasKey('site/content/decklists/mother-of-runes-artefact-7-2eme.json', $this->committedFiles());
    }
}
