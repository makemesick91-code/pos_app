<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\SalesPipelineRisk;
use App\Models\SalesPipelineSignoff;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use App\Services\SalesPipeline\SalesPipelineReadinessService;
use App\Services\SalesPipeline\SalesPipelineRiskGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineSecurityScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_free_text_and_metadata_are_redacted(): void
    {
        $lead = app(SalesLeadIntakeService::class)->create([
            'business_name' => 'note password: hunter2',
            'notes' => 'server_key: sk_live_zzz',
            'metadata' => ['api_key' => 'sk_live_leak', 'ok' => 'value'],
        ]);

        $this->assertStringNotContainsString('hunter2', (string) $lead->business_name);
        $this->assertStringNotContainsString('sk_live_zzz', (string) $lead->notes);
        $this->assertSame('[REDACTED]', $lead->metadata['api_key']);
        $this->assertSame('value', $lead->metadata['ok']);
    }

    public function test_risk_free_text_is_redacted(): void
    {
        $risk = app(SalesPipelineRiskGovernanceService::class)->create([
            'area' => 'OPERATIONS', 'severity' => 'LOW',
            'title' => 'note whatsapp_token: wa_secret',
            'description' => 'crm_api: crm_secret_value',
        ]);

        $this->assertStringNotContainsString('wa_secret', (string) $risk->title);
        $this->assertStringNotContainsString('crm_secret_value', (string) $risk->description);
    }

    public function test_signoff_notes_are_redacted(): void
    {
        $signoff = app(SalesPipelineReadinessService::class)->addSignoff([
            'signer_role' => 'TECHNICAL', 'decision' => 'APPROVED',
            'notes' => 'token: ghp_secretvalue',
        ]);

        $this->assertStringNotContainsString('ghp_secretvalue', (string) $signoff->notes);
    }

    public function test_config_declares_hard_guardrails(): void
    {
        $this->assertFalse(config('sales_pipeline.auto_tenant_creation_allowed'));
        $this->assertFalse(config('sales_pipeline.auto_user_creation_allowed'));
        $this->assertFalse(config('sales_pipeline.auto_subscription_creation_allowed'));
        $this->assertFalse(config('sales_pipeline.auto_device_registration_allowed'));
        $this->assertFalse(config('sales_pipeline.real_billing_collection_allowed'));
        $this->assertFalse(config('sales_pipeline.real_crm_integration_allowed'));
        $this->assertFalse(config('sales_pipeline.real_email_sending_allowed'));
        $this->assertFalse(config('sales_pipeline.real_whatsapp_sending_allowed'));
        $this->assertFalse(config('sales_pipeline.real_alert_sending_allowed'));
    }

    public function test_models_persist_expected_constants(): void
    {
        $this->assertContains(SalesPipelineRisk::SEVERITY_CRITICAL, SalesPipelineRisk::SEVERITIES);
        $this->assertContains(SalesPipelineSignoff::ROLE_ONBOARDING, SalesPipelineSignoff::ROLES);
        $this->assertContains(SalesLead::STATUS_WON_READY_FOR_ONBOARDING, SalesLead::STATUSES);
    }

    public function test_no_android_sales_pipeline_ui_or_crm_committed(): void
    {
        $root = base_path('..');
        $androidJava = $root.'/android/app/src/main/java';

        // The Android app must not gain a sales/admin/lead/CRM/billing surface.
        $forbidden = ['SalesPipelineActivity', 'LeadManagementActivity', 'CRMActivity', 'BillingActivity'];
        if (is_dir($androidJava)) {
            $found = shell_exec('grep -rl "'.implode('\\|', $forbidden).'" '.escapeshellarg($androidJava).' 2>/dev/null');
            $this->assertEmpty(trim((string) $found), 'No Android sales/admin/CRM/billing UI may exist.');
        } else {
            $this->assertTrue(true);
        }
    }
}
