<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_content_security_policy_is_sent_outside_local(): void
    {
        config(['broadcasting.connections.reverb' => [
            'options' => [
                'scheme' => 'https',
                'host' => 'ws.bhn.test',
                'port' => 443,
            ],
        ]]);

        $response = $this->get('/login')->assertOk();
        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("script-src 'self' 'sha256-", $policy);
        $this->assertStringContainsString('connect-src', $policy);
        $this->assertStringContainsString('wss://ws.bhn.test', $policy);

        $html = (string) $response->getContent();
        $this->assertSame(1, preg_match('#<script data-page="[^"]*" type="application/json">(.*?)</script>#s', $html, $matches));

        $expectedHash = "'sha256-".base64_encode(hash('sha256', $matches[1], true))."'";
        $this->assertStringContainsString($expectedHash, $policy);
    }

    public function test_content_security_policy_is_omitted_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    }
}
