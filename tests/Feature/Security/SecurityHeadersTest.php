<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_pages_send_baseline_headers_and_a_nonce_based_csp()
    {
        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);

        // Every script tag in the page carries the request's nonce.
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        preg_match_all('/<script\b[^>]*>/', (string) $response->getContent(), $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertTrue(str_contains($tag, 'nonce="'.$m[1].'"') || str_contains($tag, 'type="application/json"'), "Script without nonce: {$tag}");
        }
    }

    public function test_images_from_the_media_disk_and_configured_cdns_are_allowed()
    {
        config([
            'ecomodular.media_disk' => 'public',
            'filesystems.disks.public.url' => 'https://media.example-cdn.test/storage',
            'ecomodular.security.img_sources' => ['https://images.other.test/path', 'javascript:alert(1)'],
        ]);

        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('/img-src [^;]*https:\/\/media\.example-cdn\.test[ ;]/', $csp);
        $this->assertStringContainsString('https://images.other.test', $csp);
        $this->assertStringNotContainsString('javascript', $csp);
    }

    public function test_hsts_only_on_https()
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_csp_can_be_report_only_or_off()
    {
        config(['ecomodular.security.csp' => 'report-only']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeader('Content-Security-Policy-Report-Only');

        config(['ecomodular.security.csp' => 'off']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeader('X-Content-Type-Options');
    }

    public function test_api_and_json_responses_get_the_headers_too()
    {
        $this->get('/up')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_password_reset_requests_are_rate_limited_per_ip()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "user{$i}@example.test"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'another@example.test'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited_per_ip()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['name' => 'x', 'email' => 'bad'])->assertStatus(302);
        }

        $this->post('/register', ['name' => 'x', 'email' => 'bad'])->assertStatus(429);
    }
}
