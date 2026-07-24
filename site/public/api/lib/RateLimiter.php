<?php
/**
 * Fixed-window rate limiter backed by the filesystem.
 *
 * The API has no database and no Redis, so state lives in one small file per
 * client, keyed by a hash of the IP. Each file holds the current window start and
 * the request count for that window; access is serialised with flock() so
 * concurrent PHP-FPM workers cannot lose an increment.
 *
 * This is per-server, not distributed — fine for a single VPS, which is what the
 * project deploys to.
 *
 * @package PDC_API
 * @since 2.1.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class RateLimiter {

    /**
     * Probability (1 in N) that a given request also prunes expired entries.
     * Avoids both a cron dependency and a sweep on every request.
     */
    const GC_DIVISOR = 100;

    /**
     * Consume one request slot for the given client.
     *
     * @param string $client_id Opaque client identifier (e.g. IP address)
     * @param int    $limit     Max requests per window
     * @param int    $window    Window length in seconds
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public static function consume($client_id, $limit, $window) {
        $dir = PDC_RATELIMIT_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            // Never fail closed on infrastructure trouble: a validator that
            // refuses everyone because a directory is unwritable is worse than
            // one that is briefly un-throttled. The failure is logged.
            error_log('PDC RateLimiter: cannot create ' . $dir);
            return array('allowed' => true, 'remaining' => $limit, 'retry_after' => 0);
        }

        if (function_exists('random_int') && random_int(1, self::GC_DIVISOR) === 1) {
            self::gc($window);
        }

        $path   = $dir . '/' . hash('sha256', $client_id) . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            error_log('PDC RateLimiter: cannot open ' . $path);
            return array('allowed' => true, 'remaining' => $limit, 'retry_after' => 0);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                error_log('PDC RateLimiter: cannot lock ' . $path);
                return array('allowed' => true, 'remaining' => $limit, 'retry_after' => 0);
            }

            $now      = time();
            $contents = stream_get_contents($handle);
            $state    = json_decode($contents, true);

            $window_start = (is_array($state) && isset($state['start'])) ? (int) $state['start'] : 0;
            $count        = (is_array($state) && isset($state['count'])) ? (int) $state['count'] : 0;

            // Start a fresh window if the previous one has elapsed.
            if ($window_start === 0 || ($now - $window_start) >= $window) {
                $window_start = $now;
                $count        = 0;
            }

            $count++;
            $allowed     = $count <= $limit;
            $retry_after = $allowed ? 0 : max(1, ($window_start + $window) - $now);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(array('start' => $window_start, 'count' => $count)));
            fflush($handle);
            flock($handle, LOCK_UN);

            return array(
                'allowed'     => $allowed,
                'remaining'   => max(0, $limit - $count),
                'retry_after' => $retry_after,
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * Identify the client.
     *
     * Uses REMOTE_ADDR only. X-Forwarded-For is deliberately ignored: it is
     * client-controlled, so trusting it would let anyone reset their own limit by
     * spoofing the header. If the API is ever put behind a reverse proxy or CDN,
     * this needs a trusted-proxy allow-list before reading that header.
     *
     * @return string
     */
    public static function client_id() {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /**
     * Delete entries whose window elapsed long ago.
     *
     * @param int $window Window length in seconds
     */
    private static function gc($window) {
        $cutoff = time() - ($window * 10);
        foreach (glob(PDC_RATELIMIT_DIR . '/*.json') as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
