<?php

use PHPUnit\Framework\TestCase;

/**
 * The controller wires the guards (honeypot, rate limit, Turnstile) then
 * DecklistSubmission + DeckValidator + GitHubClient. All network is stubbed;
 * DeckValidator reads through the fixture-seeded Scryfall cache and real ban list.
 *
 * Each test uses a unique client id so the file-backed rate limiter can't bleed
 * between tests.
 */
class DecklistSubmissionControllerTest extends TestCase
{
    private $calls;          // GitHub calls
    private $responses;      // queued GitHub responses
    private $turnstileCalls; // Turnstile calls

    protected function setUp(): void
    {
        $this->calls = array();
        $this->responses = array();
        $this->turnstileCalls = array();
    }

    private function ip(string $suffix = ''): string
    {
        return '203.0.113.' . crc32($this->getName() . $suffix) % 250;
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
        $calls =& $this->turnstileCalls;
        $http = function ($url, $fields) use (&$calls, $ok) {
            $calls[] = $fields;
            return array('success' => $ok);
        };
        return new TurnstileVerifier('secret', $http);
    }

    private function controller(bool $turnstileOk = true): DecklistSubmissionController
    {
        return new DecklistSubmissionController($this->turnstile($turnstileOk), $this->github());
    }

    private function queueFullPrFlow(): void
    {
        $this->responses = array(
            array('status' => 200, 'body' => array('object' => array('sha' => 'base'))),
            array('status' => 200, 'body' => array('tree' => array('sha' => 'bt'))),
            array('status' => 201, 'body' => array('sha' => 'nt')),
            array('status' => 201, 'body' => array('sha' => 'nc')),
            array('status' => 201, 'body' => array('ref' => 'refs/heads/x')),
            array('status' => 201, 'body' => array('number' => 7, 'html_url' => 'https://github.com/owner/repo/pull/7')),
        );
    }

    private function withTurnstile(array $in): array
    {
        return array_merge(array('cf-turnstile-response' => 'tok'), $in);
    }

    // -- happy path ----------------------------------------------------------

    public function testHappyPathOpensPrWithDecklistJson(): void
    {
        $this->queueFullPrFlow();

        $res = $this->controller()->handle(
            $this->withTurnstile(array('commander' => 'Mother of Runes', 'decklist' => '99 Plains')),
            $this->ip()
        );

        $this->assertSame(200, $res['status']);
        $this->assertSame('https://github.com/owner/repo/pull/7', $res['body']['pr_url']);

        $treeCalls = array_values(array_filter($this->calls, function ($c) {
            return substr($c['url'], -strlen('/git/trees')) === '/git/trees';
        }));
        $this->assertCount(1, $treeCalls);
        $this->assertStringStartsWith('site/content/decklists/', $treeCalls[0]['json']['tree'][0]['path']);
        $this->assertStringContainsString('"commander": "Mother of Runes"', $treeCalls[0]['json']['tree'][0]['content']);
    }

    // -- guards --------------------------------------------------------------

    public function testHoneypotFilledIsRejectedBeforeAnything(): void
    {
        $res = $this->controller()->handle(
            $this->withTurnstile(array('commander' => 'Mother of Runes', 'decklist' => '99 Plains', 'website' => 'spam')),
            $this->ip()
        );

        $this->assertSame(400, $res['status']);
        $this->assertSame(array(), $this->calls, 'GitHub must not be called');
        $this->assertSame(array(), $this->turnstileCalls, 'Turnstile must not even be reached');
    }

    public function testBadTurnstileReturns403AndOpensNoPr(): void
    {
        $res = $this->controller(false)->handle(
            $this->withTurnstile(array('commander' => 'Mother of Runes', 'decklist' => '99 Plains')),
            $this->ip()
        );

        $this->assertSame(403, $res['status']);
        $this->assertSame('captcha', $res['body']['error']);
        $this->assertSame(array(), $this->calls);
    }

    public function testRateLimitedAfterQuota(): void
    {
        $ip = $this->ip();
        $ctrl = $this->controller();
        // PDC_SUBMIT_RATE_LIMIT = 5. Use bad-form input so allowed calls stop at
        // the shape step (422) without needing GitHub responses.
        $bad = $this->withTurnstile(array('decklist' => '99 Plains')); // no commander
        for ($i = 1; $i <= 5; $i++) {
            $this->assertSame(422, $ctrl->handle($bad, $ip)['status'], "call $i should pass the rate limit");
        }
        $sixth = $ctrl->handle($bad, $ip);
        $this->assertSame(429, $sixth['status']);
        $this->assertArrayHasKey('retry_after', $sixth['body']);
    }

    // -- validation ----------------------------------------------------------

    public function testIllegalDeckReturns422AndOpensNoPr(): void
    {
        $res = $this->controller()->handle(
            $this->withTurnstile(array('commander' => 'Mother of Runes', 'decklist' => "98 Plains\n1 Goliath Paladin")),
            $this->ip()
        );

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid', $res['body']['error']);
        $this->assertFalse($res['body']['data']['is_valid']);
        $this->assertSame(array(), $this->calls, 'a banned card never becomes a PR');
    }

    public function testBadFormReturns422AndOpensNoPr(): void
    {
        $res = $this->controller()->handle(
            $this->withTurnstile(array('decklist' => '99 Plains')), // no commander
            $this->ip()
        );

        $this->assertSame(422, $res['status']);
        $this->assertSame('form', $res['body']['error']);
        $this->assertSame(array(), $this->calls);
    }
}
