<?php

use PHPUnit\Framework\TestCase;

/** Fail-closed captcha verification, HTTP stubbed (no network). */
class TurnstileVerifierTest extends TestCase
{
    private function verifier($httpResult, &$called = null): TurnstileVerifier
    {
        $called = false;
        $http = function ($url, $fields) use ($httpResult, &$called) {
            $called = true;
            return $httpResult;
        };
        return new TurnstileVerifier('secret', $http);
    }

    public function testTrueWhenCloudflareSucceeds(): void
    {
        $this->assertTrue($this->verifier(array('success' => true))->verify('token', '1.2.3.4'));
    }

    public function testFalseWhenCloudflareFails(): void
    {
        $this->assertFalse($this->verifier(array('success' => false, 'error-codes' => array('invalid-input-response')))->verify('token', '1.2.3.4'));
    }

    public function testFailsClosedOnNetworkError(): void
    {
        // Transport returns [] on failure.
        $this->assertFalse($this->verifier(array())->verify('token', '1.2.3.4'));
    }

    public function testFalseOnEmptyTokenWithoutCallingCloudflare(): void
    {
        $v = $this->verifier(array('success' => true), $called);
        $this->assertFalse($v->verify('', '1.2.3.4'));
        $this->assertFalse($called, 'no point verifying an empty token');
    }

    public function testFailsClosedWhenSecretMissing(): void
    {
        $called = false;
        $http = function ($url, $fields) use (&$called) { $called = true; return array('success' => true); };
        $v = new TurnstileVerifier('', $http);   // misconfigured
        $this->assertFalse($v->verify('token', '1.2.3.4'));
        $this->assertFalse($called);
    }
}
