<?php

use PHPUnit\Framework\TestCase;

/**
 * The controller wires DecklistSubmission + DeckValidator + GitHubClient.
 * GitHubClient runs against a stubbed HTTP transport (no network); DeckValidator
 * reads through the fixture-seeded Scryfall cache and the real ban list.
 */
class DecklistSubmissionControllerTest extends TestCase
{
    private $calls;
    private $responses;

    protected function setUp(): void
    {
        $this->calls = array();
        $this->responses = array();
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

    /** Queue the 6 responses a full submit needs: ref, base commit, tree, commit, ref, PR. */
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

    public function testHappyPathOpensPrWithDecklistJson(): void
    {
        $this->queueFullPrFlow();
        $ctrl = new DecklistSubmissionController($this->github());

        $res = $ctrl->handle(array('commander' => 'Mother of Runes', 'decklist' => '99 Plains'), '1.2.3.4');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertSame('https://github.com/owner/repo/pull/7', $res['body']['pr_url']);

        // The committed file is a decklist JSON under the decklists dir.
        $treeCalls = array_values(array_filter($this->calls, function ($c) {
            return substr($c['url'], -strlen('/git/trees')) === '/git/trees';
        }));
        $this->assertCount(1, $treeCalls);
        $path = $treeCalls[0]['json']['tree'][0]['path'];
        $content = $treeCalls[0]['json']['tree'][0]['content'];
        $this->assertStringStartsWith('site/content/decklists/', $path);
        $this->assertStringContainsString('"commander": "Mother of Runes"', $content);

        // A PR was opened.
        $last = end($this->calls);
        $this->assertStringEndsWith('/pulls', $last['url']);
    }

    public function testIllegalDeckReturns422AndOpensNoPr(): void
    {
        $ctrl = new DecklistSubmissionController($this->github());

        $res = $ctrl->handle(array(
            'commander' => 'Mother of Runes',
            'decklist'  => "98 Plains\n1 Goliath Paladin", // Goliath Paladin is banned
        ), '1.2.3.4');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid', $res['body']['error']);
        $this->assertFalse($res['body']['data']['is_valid']);
        // Garbage never becomes a PR: GitHub was never called.
        $this->assertSame(array(), $this->calls);
    }

    public function testBadFormReturns422AndOpensNoPr(): void
    {
        $ctrl = new DecklistSubmissionController($this->github());

        $res = $ctrl->handle(array('decklist' => '99 Plains'), '1.2.3.4'); // no commander

        $this->assertSame(422, $res['status']);
        $this->assertSame('form', $res['body']['error']);
        $this->assertSame(array(), $this->calls);
    }
}
