<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Uix2PublicExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_premium_sections_and_valid_ctas(): void
    {
        $response = $this->get('/')->assertOk();
        foreach (['fitur', 'produk', 'cara-kerja', 'solusi', 'offline', 'pembayaran', 'harga', 'faq', 'interest'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        $response->assertSee('action="/interest"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('Dalam tahap pilot')
            ->assertDontSee('Aish POS Lite')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('/api/v1/admin');
    }

    public function test_public_copy_does_not_make_unsupported_https_or_customer_claims(): void
    {
        $content = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('10.000 pelanggan', $content);
        $this->assertStringNotContainsString('HTTPS production', $content);
        $this->assertStringContainsString('pilot terbatas', $content);
    }
}
