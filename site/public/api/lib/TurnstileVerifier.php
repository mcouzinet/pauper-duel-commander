<?php
/**
 * Cloudflare Turnstile server-side verification.
 *
 * Fail-CLOSED: any failure — bad token, Cloudflare says no, or the network is
 * down — returns false, so a submission is never accepted on an unverified
 * captcha. (Contrast RateLimiter, which fails open.) HTTP transport injectable.
 *
 * @package PDC_API
 * @since 2.2.0
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class TurnstileVerifier {

    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @var string */
    private $secret;
    /** @var callable fn(string $url, array $fields): array decoded body ([] on failure) */
    private $http;

    public function __construct($secret, ?callable $http = null) {
        $this->secret = $secret;
        $this->http   = $http ?? array($this, 'curl_transport');
    }

    /**
     * @param string      $token     The cf-turnstile-response from the form
     * @param string|null $remote_ip Client IP (optional, passed to Cloudflare)
     * @return bool true only if Cloudflare confirms success
     */
    public function verify($token, $remote_ip = null) {
        if (!is_string($token) || $token === '' || !$this->secret) {
            return false;   // nothing to verify, or misconfigured -> fail closed
        }
        $body = call_user_func($this->http, self::VERIFY_URL, array(
            'secret'   => $this->secret,
            'response' => $token,
            'remoteip' => (string) $remote_ip,
        ));
        return is_array($body) && !empty($body['success']);
    }

    /**
     * @return array Decoded JSON body, or [] on any transport failure.
     */
    private function curl_transport($url, array $fields) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false) {
            return array();
        }
        $body = json_decode($raw, true);
        return is_array($body) ? $body : array();
    }
}
