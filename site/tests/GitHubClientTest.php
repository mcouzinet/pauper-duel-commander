<?php

use PHPUnit\Framework\TestCase;

/**
 * Hermetic: the HTTP transport is stubbed, so no network. We assert GitHubClient
 * makes the right sequence of GitHub API calls with the right payloads, and that
 * a non-2xx response becomes an exception.
 */
class GitHubClientTest extends TestCase
{
    /** @var array recorded calls: [method, url, json] */
    private $calls;
    /** @var array queued responses to return in order */
    private $responses;

    protected function setUp(): void
    {
        $this->calls = array();
        $this->responses = array();
    }

    /** Build a client whose transport records calls and replays queued responses. */
    private function client(): GitHubClient
    {
        $calls =& $this->calls;
        $responses =& $this->responses;
        $http = function ($method, $url, $json) use (&$calls, &$responses) {
            $calls[] = array('method' => $method, 'url' => $url, 'json' => $json);
            return array_shift($responses) ?: array('status' => 500, 'body' => array());
        };
        return new GitHubClient('tok', 'owner/repo', $http);
    }

    private function queue($status, array $body): void
    {
        $this->responses[] = array('status' => $status, 'body' => $body);
    }

    public function testBaseShaReadsTheBranchRef(): void
    {
        $this->queue(200, array('object' => array('sha' => 'abc123')));

        $sha = $this->client()->base_sha('main');

        $this->assertSame('abc123', $sha);
        $this->assertSame('GET', $this->calls[0]['method']);
        $this->assertStringEndsWith('/repos/owner/repo/git/ref/heads/main', $this->calls[0]['url']);
    }

    public function testCommitFilesBuildsTreeCommitAndBranch(): void
    {
        // Responses in call order: base commit, new tree, new commit, new ref.
        $this->queue(200, array('tree' => array('sha' => 'basetree')));
        $this->queue(201, array('sha' => 'newtree'));
        $this->queue(201, array('sha' => 'newcommit'));
        $this->queue(201, array('ref' => 'refs/heads/submission/x'));

        $sha = $this->client()->commit_files(
            'submission/x',
            'base123',
            array('site/content/decklists/a.json' => '{"title":"A"}'),
            'Add decklist A'
        );

        $this->assertSame('newcommit', $sha);
        $this->assertCount(4, $this->calls);

        // 1. read the base commit to get its tree
        $this->assertSame('GET', $this->calls[0]['method']);
        $this->assertStringEndsWith('/git/commits/base123', $this->calls[0]['url']);

        // 2. create a tree layered on the base tree, with our file
        $tree = $this->calls[1];
        $this->assertSame('POST', $tree['method']);
        $this->assertStringEndsWith('/git/trees', $tree['url']);
        $this->assertSame('basetree', $tree['json']['base_tree']);
        $this->assertSame('site/content/decklists/a.json', $tree['json']['tree'][0]['path']);
        $this->assertSame('{"title":"A"}', $tree['json']['tree'][0]['content']);
        $this->assertSame('100644', $tree['json']['tree'][0]['mode']);
        $this->assertSame('blob', $tree['json']['tree'][0]['type']);

        // 3. commit pointing at the new tree, parented on base123
        $commit = $this->calls[2];
        $this->assertStringEndsWith('/git/commits', $commit['url']);
        $this->assertSame('newtree', $commit['json']['tree']);
        $this->assertSame(array('base123'), $commit['json']['parents']);
        $this->assertSame('Add decklist A', $commit['json']['message']);

        // 4. create the branch ref at the new commit
        $ref = $this->calls[3];
        $this->assertStringEndsWith('/git/refs', $ref['url']);
        $this->assertSame('refs/heads/submission/x', $ref['json']['ref']);
        $this->assertSame('newcommit', $ref['json']['sha']);
    }

    public function testOpenPullRequestReturnsNumberAndUrl(): void
    {
        $this->queue(201, array('number' => 42, 'html_url' => 'https://github.com/owner/repo/pull/42'));

        $pr = $this->client()->open_pull_request('submission/x', 'main', 'Titre', 'Corps');

        $this->assertSame(42, $pr['number']);
        $this->assertSame('https://github.com/owner/repo/pull/42', $pr['html_url']);
        $call = $this->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertStringEndsWith('/repos/owner/repo/pulls', $call['url']);
        $this->assertSame('submission/x', $call['json']['head']);
        $this->assertSame('main', $call['json']['base']);
        $this->assertSame('Titre', $call['json']['title']);
    }

    public function testNonSuccessResponseThrows(): void
    {
        $this->queue(403, array('message' => 'Resource not accessible by personal access token'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('403');

        $this->client()->base_sha('main');
    }
}
