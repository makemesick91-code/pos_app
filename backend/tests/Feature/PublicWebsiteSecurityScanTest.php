<?php

namespace Tests\Feature;

use App\Models\PublicWebsiteRisk;
use App\Models\PublicWebsiteSignoff;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use App\Services\PublicWebsite\PublicWebsiteRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteSecurityScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_free_text_and_metadata_are_redacted(): void
    {
        $risk = app(PublicWebsiteRiskGovernanceService::class)->create([
            'area' => 'SECURITY', 'severity' => 'LOW',
            'title' => 'note password: hunter2',
            'description' => 'server_key: sk_live_zzz',
            'metadata' => ['api_key' => 'sk_live_leak', 'ok' => 'value'],
        ]);

        $this->assertStringNotContainsString('hunter2', $risk->title);
        $this->assertStringNotContainsString('sk_live_zzz', (string) $risk->description);
        $this->assertSame('[REDACTED]', $risk->metadata['api_key']);
        $this->assertSame('value', $risk->metadata['ok']);
    }

    public function test_signoff_notes_are_redacted(): void
    {
        $signoff = app(PublicWebsiteReadinessService::class)->addSignoff([
            'signer_role' => 'TECHNICAL', 'decision' => 'APPROVED',
            'notes' => 'token: ghp_secretvalue',
        ]);

        $this->assertStringNotContainsString('ghp_secretvalue', (string) $signoff->notes);
    }

    public function test_config_declares_hard_guardrails(): void
    {
        $this->assertFalse(config('public_website.live_tracking_tokens_allowed'));
        $this->assertFalse(config('public_website.public_self_service_signup_allowed'));
        $this->assertFalse(config('public_website.real_billing_collection_allowed'));
    }

    public function test_models_persist_expected_constants(): void
    {
        $this->assertContains(PublicWebsiteRisk::SEVERITY_CRITICAL, PublicWebsiteRisk::SEVERITIES);
        $this->assertContains(PublicWebsiteSignoff::ROLE_LEGAL_PRIVACY, PublicWebsiteSignoff::ROLES);
    }
}
