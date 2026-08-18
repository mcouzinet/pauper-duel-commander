<?php
/**
 * Minimal GitHub REST client for opening submission pull requests.
 *
 * Creates a branch, commits one or more files in a single commit (Git Trees API),
 * and opens a PR. The HTTP transport is injectable so the whole thing is testable
 * without touching the network.
 *
 * Auth: a fine-grained PAT scoped to this repo with contents + pull_requests
 * write only. It can open PRs; it cannot merge or run workflows.
 *
 * @package PDC_API
 * @since 2.2.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class GitHubClient {

    const API_BASE = 'https://api.github.com';

    /** @var string */
    private $token;
    /** @var string "owner/name" */
    private $repo;
    /** @var callable fn(string $method, string $url, ?array $json): array{status:int, body:array} */
    private $http;

    /**
     * @param string        $token Fine-grained PAT
     * @param string        $repo  "owner/name"
     * @param callable|null $http  Transport override for tests
     */
    public function __construct($token, $repo, ?callable $http = null) {
        $this->token = $token;
        $this->repo  = $repo;
        $this->http  = $http ?? array($this, 'curl_transport');
    }

    /**
     * Head commit SHA of a branch.
     *
     * @param string $branch
     * @return string
     */
    public function base_sha($branch = 'main') {
        $res = $this->request('GET', "/repos/{$this->repo}/git/ref/heads/{$branch}");
        return $res['object']['sha'];
    }

    /**
     * Commit files on a NEW branch, in a single commit.
     *
     * @param string                $branch   New branch name (must not exist yet)
     * @param string                $base_sha Commit the new branch starts from
     * @param array<string,string>  $files    repo-relative path => file contents
     * @param string                $message  Commit message
     * @return string New commit SHA
     */
    public function commit_files($branch, $base_sha, array $files, $message) {
        // Base tree of the starting commit.
        $base_commit = $this->request('GET', "/repos/{$this->repo}/git/commits/{$base_sha}");
        $base_tree   = $base_commit['tree']['sha'];

        // New tree with the submitted files layered on top.
        $tree_items = array();
        foreach ($files as $path => $contents) {
            $tree_items[] = array(
                'path'    => $path,
                'mode'    => '100644',
                'type'    => 'blob',
                'content' => $contents,
            );
        }
        $tree = $this->request('POST', "/repos/{$this->repo}/git/trees", array(
            'base_tree' => $base_tree,
            'tree'      => $tree_items,
        ));

        // Commit pointing at the new tree.
        $commit = $this->request('POST', "/repos/{$this->repo}/git/commits", array(
            'message' => $message,
            'tree'    => $tree['sha'],
            'parents' => array($base_sha),
        ));

        // Create the branch ref at that commit.
        $this->request('POST', "/repos/{$this->repo}/git/refs", array(
            'ref' => "refs/heads/{$branch}",
            'sha' => $commit['sha'],
        ));

        return $commit['sha'];
    }

    /**
     * Open a pull request.
     *
     * @return array{number:int, html_url:string}
     */
    public function open_pull_request($head, $base, $title, $body, array $labels = array()) {
        $pr = $this->request('POST', "/repos/{$this->repo}/pulls", array(
            'title' => $title,
            'head'  => $head,
            'base'  => $base,
            'body'  => $body,
        ));

        if ($labels) {
            $this->label($pr['number'], $labels);
        }

        return array('number' => $pr['number'], 'html_url' => $pr['html_url']);
    }

    /**
     * Label a pull request. Best effort, and deliberately so.
     *
     * Labels cannot be set when creating the PR — GitHub only takes them on the
     * issues endpoint — so this is a second call, made once the submission has
     * already succeeded. Letting it throw would report a failure for a PR that
     * exists, and the submitter would send the whole thing again. A missing
     * label is a cosmetic loss; a duplicate submission is not.
     *
     * @param int      $number Pull request number
     * @param string[] $labels Label names, which must already exist in the repo
     */
    public function label($number, array $labels) {
        try {
            $this->request(
                'POST',
                "/repos/{$this->repo}/issues/{$number}/labels",
                array('labels' => array_values($labels))
            );
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Perform a request and return the decoded body, or throw on a non-2xx status.
     *
     * @return array Decoded JSON body
     * @throws RuntimeException on transport failure or API error
     */
    private function request($method, $path, ?array $json = null) {
        $url = self::API_BASE . $path;
        $res = call_user_func($this->http, $method, $url, $json);
        $status = isset($res['status']) ? (int) $res['status'] : 0;
        $body   = isset($res['body']) && is_array($res['body']) ? $res['body'] : array();

        if ($status < 200 || $status >= 300) {
            $msg = isset($body['message']) ? $body['message'] : 'unknown error';
            throw new RuntimeException("GitHub API {$method} {$path}: {$status} {$msg}");
        }
        return $body;
    }

    /**
     * Default cURL transport.
     *
     * @return array{status:int, body:array}
     */
    private function curl_transport($method, $url, ?array $json) {
        $ch = curl_init($url);
        $headers = array(
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: PDC-Submissions/1.0',
        );
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = $raw === false ? array() : json_decode($raw, true);
        return array('status' => (int) $status, 'body' => is_array($body) ? $body : array());
    }
}
