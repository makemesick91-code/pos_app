<?php

namespace Tests\Feature;

use App\Models\SaasBillingCollectionRisk;
use App\Models\SaasBillingCollectionSignoff;
use App\Models\SaasBillingInvoice;
use App\Models\SaasBillingPaymentEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCollectionSecurityScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_declares_hard_guardrails(): void
    {
        $this->assertFalse(config('billing_collection.real_payment_gateway_allowed'));
        $this->assertFalse(config('billing_collection.auto_charge_allowed'));
        $this->assertFalse(config('billing_collection.subscription_payment_automation_allowed'));
        $this->assertFalse(config('billing_collection.auto_tenant_suspension_allowed'));
        $this->assertFalse(config('billing_collection.auto_subscription_renewal_allowed'));
        $this->assertFalse(config('billing_collection.public_payment_link_allowed'));
        $this->assertFalse(config('billing_collection.real_invoice_email_sending_allowed'));
        $this->assertFalse(config('billing_collection.real_whatsapp_sending_allowed'));
        $this->assertFalse(config('billing_collection.real_crm_integration_allowed'));
        $this->assertFalse(config('billing_collection.real_accounting_integration_allowed'));
    }

    public function test_models_persist_expected_constants(): void
    {
        $this->assertContains(SaasBillingInvoice::STATUS_VOIDED, SaasBillingInvoice::STATUSES);
        $this->assertContains(SaasBillingPaymentEvidence::METHOD_MANUAL_QRIS_REFERENCE, SaasBillingPaymentEvidence::METHODS);
        $this->assertContains(SaasBillingCollectionRisk::SEVERITY_CRITICAL, SaasBillingCollectionRisk::SEVERITIES);
        $this->assertContains(SaasBillingCollectionSignoff::ROLE_FINANCE, SaasBillingCollectionSignoff::ROLES);
    }

    public function test_backend_billing_source_has_no_real_gateway_or_messaging(): void
    {
        $backend = base_path('app');
        $forbidden = [
            'MIDTRANS_SERVER_KEY', 'XENDIT_SECRET_KEY', 'DUITKU_API_KEY',
            'Http::post', 'Mail::send', 'Mail::to', 'Notification::send',
        ];
        $found = shell_exec('grep -rl "'.implode('\\|', $forbidden).'" '.escapeshellarg($backend.'/Services/BillingCollection').' 2>/dev/null');
        $this->assertEmpty(trim((string) $found), 'Billing collection services must not call a real gateway or send real messages.');
    }

    public function test_no_android_billing_ui_or_gateway_committed(): void
    {
        $root = base_path('..');
        $androidJava = $root.'/android/app/src/main/java';

        $forbidden = [
            'BillingActivity', 'BillingCollectionActivity', 'InvoiceActivity',
            'PaymentEvidenceActivity', 'AdminBillingActivity', 'SaaSBillingActivity',
            'CRMActivity', 'AccountingActivity', 'TenantSuspensionActivity',
        ];
        if (is_dir($androidJava)) {
            $found = shell_exec('grep -rl "'.implode('\\|', $forbidden).'" '.escapeshellarg($androidJava).' 2>/dev/null');
            $this->assertEmpty(trim((string) $found), 'No Android billing/admin/CRM UI may exist.');
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_docs_have_no_real_secrets(): void
    {
        $root = base_path('..');
        $docs = $root.'/docs/billing-collection';
        $found = shell_exec('grep -rlE "sk_live_|ghp_[A-Za-z0-9]{20}|SERVER_KEY=" '.escapeshellarg($docs).' 2>/dev/null');
        $this->assertEmpty(trim((string) $found), 'Billing collection docs must not contain real secrets.');
    }
}
