<?php

namespace Tests\Feature;

use App\Services\PaymentGateway\PaymentGatewayGoNoGoService;
use App\Services\PaymentGateway\PaymentGatewayGovernanceAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 31 — the config exposes PGW-R001..R018, safe defaults, and the audit/
 * go-no-go are GO on a clean migrated state (PGW-R001/R002/R016/R017).
 */
class PaymentGatewayGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pgw_rules_are_present(): void
    {
        $rules = (array) config('payment_gateway_governance.rules');
        for ($i = 1; $i <= 18; $i++) {
            $this->assertArrayHasKey(sprintf('PGW-R%03d', $i), $rules);
        }
    }

    public function test_safe_defaults(): void
    {
        $this->assertSame('mock', config('payment_gateway_governance.default_provider'));
        $this->assertFalse((bool) config('payment_gateway_governance.live_gateway_enabled'));
        $this->assertFalse((bool) config('payment_gateway_governance.allow_partial_payment'));
        $this->assertFalse((bool) config('payment_gateway_governance.allow_overpayment'));
        $this->assertTrue((bool) config('payment_gateway_governance.webhook_signature_required'));
        $this->assertTrue((bool) config('payment_gateway_governance.replay_protection_required'));
        $this->assertTrue((bool) config('payment_gateway_governance.idempotency_required'));
    }

    public function test_all_guardrail_flags_are_false(): void
    {
        foreach ([
            'live_gateway_call_in_ci_allowed',
            'unsigned_webhook_allowed',
            'failed_event_marks_invoice_paid_allowed',
            'settlement_bypasses_collection_service_allowed',
            'settlement_lifts_manual_suspension_allowed',
            'tenant_route_can_mutate_gateway_state_allowed',
            'secrets_in_gateway_metadata_allowed',
            'duplicate_provider_reference_settlement_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('payment_gateway_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_config_contains_no_secret_values(): void
    {
        $encoded = (string) json_encode(config('payment_gateway_governance'));
        // Only env variable NAMES may appear — never a value that looks like a key.
        $this->assertDoesNotMatchRegularExpression('/SB-Mid-server-|xnd_|sk_live_|sk_test_/', $encoded);
    }

    public function test_governance_audit_is_go_on_clean_state(): void
    {
        $report = app(PaymentGatewayGovernanceAuditService::class)->evaluate();
        $this->assertSame(PaymentGatewayGovernanceAuditService::DECISION_GO, $report['decision']);
    }

    public function test_go_no_go_is_go_on_clean_state(): void
    {
        $report = app(PaymentGatewayGoNoGoService::class)->evaluate();
        $this->assertSame(PaymentGatewayGoNoGoService::DECISION_GO, $report['decision']);
    }
}
