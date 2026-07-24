<?php

use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    /** Unique per test so cases cannot bleed into each other. */
    private function client(string $suffix = ''): string
    {
        return '198.51.100.7|' . $this->getName() . $suffix;
    }

    public function testAllowsRequestsUpToTheLimit(): void
    {
        $client = $this->client();

        for ($i = 1; $i <= 5; $i++) {
            $result = RateLimiter::consume($client, 5, 60);
            $this->assertTrue($result['allowed'], "request $i should be allowed");
            $this->assertSame(5 - $i, $result['remaining']);
        }
    }

    public function testBlocksOnceTheLimitIsExceeded(): void
    {
        $client = $this->client();

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::consume($client, 3, 60);
        }

        $result = RateLimiter::consume($client, 3, 60);

        $this->assertFalse($result['allowed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['retry_after'], 'a blocked client must be told when to retry');
        $this->assertLessThanOrEqual(60, $result['retry_after']);
    }

    public function testClientsAreCountedIndependently(): void
    {
        $a = $this->client('-a');
        $b = $this->client('-b');

        RateLimiter::consume($a, 1, 60);
        $blockedA = RateLimiter::consume($a, 1, 60);
        $freshB   = RateLimiter::consume($b, 1, 60);

        $this->assertFalse($blockedA['allowed']);
        $this->assertTrue($freshB['allowed'], 'one client hitting the limit must not affect another');
    }

    public function testWindowResetsAfterItElapses(): void
    {
        $client = $this->client();

        // A 1-second window keeps the test fast while still exercising the reset.
        $this->assertTrue(RateLimiter::consume($client, 1, 1)['allowed']);
        $this->assertFalse(RateLimiter::consume($client, 1, 1)['allowed']);

        sleep(2);

        $this->assertTrue(
            RateLimiter::consume($client, 1, 1)['allowed'],
            'the counter must reset once the window has elapsed'
        );
    }

    public function testStateIsKeyedByHashNotRawIp(): void
    {
        // The store must not become a plaintext list of visitor IPs on disk.
        $client = $this->client();
        RateLimiter::consume($client, 5, 60);

        $files = glob(PDC_RATELIMIT_DIR . '/*.json');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.json$/', basename($file));
            $this->assertStringNotContainsString('198.51.100.7', file_get_contents($file));
        }
    }

    public function testClientIdFallsBackWhenRemoteAddrIsAbsent(): void
    {
        $original = $_SERVER['REMOTE_ADDR'] ?? null;
        unset($_SERVER['REMOTE_ADDR']);

        try {
            $this->assertSame('unknown', RateLimiter::client_id());
        } finally {
            if ($original !== null) {
                $_SERVER['REMOTE_ADDR'] = $original;
            }
        }
    }

    /**
     * X-Forwarded-For is client-controlled: honouring it would let anyone reset
     * their own limit by sending a different value each request.
     */
    public function testForwardedHeaderIsIgnored(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';

        try {
            $this->assertSame('203.0.113.9', RateLimiter::client_id());
        } finally {
            unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
        }
    }
}
