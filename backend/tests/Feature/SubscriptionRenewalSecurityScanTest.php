<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 24 — static guardrail scan. Asserts no forbidden automation (real
 * payment gateway, auto-charge, auto-suspension, auto-renewal, real message
 * sending), no secrets committed, and no Android renewal/dunning UI.
 */
class SubscriptionRenewalSecurityScanTest extends TestCase
{
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    public function test_all_automation_guardrails_are_false(): void
    {
        $flags = [
            'real_payment_gateway_allowed', 'auto_charge_allowed',
            'subscription_payment_automation_allowed', 'auto_tenant_suspension_allowed',
            'auto_tenant_reactivation_allowed', 'auto_subscription_renewal_allowed',
            'auto_plan_change_allowed', 'auto_device_limit_change_allowed',
            'public_renewal_portal_allowed', 'public_payment_link_allowed',
            'real_email_sending_allowed', 'real_whatsapp_sending_allowed',
            'real_sms_sending_allowed', 'real_alert_sending_allowed',
            'real_crm_integration_allowed', 'real_accounting_integration_allowed',
        ];

        foreach ($flags as $flag) {
            $this->assertFalse((bool) config('subscription_renewal.'.$flag), "{$flag} must be false");
        }
    }

    public function test_service_sources_do_not_reference_real_senders_or_gateways(): void
    {
        $dir = base_path('app/Services/SubscriptionRenewal');
        $forbidden = ['Mail::send', 'Http::post', 'Http::get', 'MIDTRANS_SERVER_KEY', 'XENDIT_SECRET_KEY', 'twilio', 'Notification::route'];

        foreach (glob($dir.'/*.php') as $file) {
            $contents = (string) file_get_contents($file);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $contents, basename($file)." must not reference {$needle}");
            }
        }
    }

    public function test_no_android_renewal_or_dunning_ui(): void
    {
        $androidJava = $this->repoRoot().'/android/app/src/main/java';
        if (! is_dir($androidJava)) {
            $this->markTestSkipped('Android source not present.');
        }

        $matches = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($androidJava));
        foreach ($rii as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.kt') && ! str_ends_with($file->getFilename(), '.java')) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            foreach (['RenewalActivity', 'DunningActivity', 'SubscriptionRenewalActivity', 'AdminRenewalActivity'] as $needle) {
                if (str_contains($contents, $needle)) {
                    $matches[] = $needle;
                }
            }
        }

        $this->assertSame([], $matches, 'No Android renewal/dunning UI may exist.');
    }
}
