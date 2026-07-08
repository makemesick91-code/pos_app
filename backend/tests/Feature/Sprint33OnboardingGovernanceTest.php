<?php

namespace Tests\Feature;

use App\Services\TenantOnboarding\OnboardingGoNoGoService;
use App\Services\TenantOnboarding\OnboardingGovernanceAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Sprint33OnboardingGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_onb_rules_present_in_config(): void
    {
        $rules = config('onboarding_governance.rules');

        for ($i = 1; $i <= 26; $i++) {
            $this->assertArrayHasKey(sprintf('ONB-R%03d', $i), $rules);
        }
    }

    public function test_onb_rules_present_in_pos_foundation(): void
    {
        $rules = config('pos_foundation.onboarding_runtime_rules_sprint_33');

        $this->assertArrayHasKey('ONB-R001', $rules);
        $this->assertArrayHasKey('ONB-R013', $rules);
        $this->assertArrayHasKey('ONB-R026', $rules);
    }

    public function test_onb_rules_present_in_project_rules_doc(): void
    {
        $doc = file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString('ONB-R001', $doc);
        $this->assertStringContainsString('ONB-R018', $doc);
        $this->assertStringContainsString('ONB-R026', $doc);
    }

    public function test_hard_guardrails_are_locked_false(): void
    {
        foreach ([
            'unknown_plan_grants_unlimited_allowed',
            'onboarding_bypasses_entitlement_service_allowed',
            'onboarding_marks_invoice_paid_directly_allowed',
            'failed_payment_activates_paid_access_allowed',
            'paid_invoice_lifts_manual_suspension_allowed',
            'public_route_can_mutate_onboarding_lifecycle_allowed',
            'tenant_route_can_mutate_onboarding_lifecycle_allowed',
            'raw_credential_in_output_allowed',
        ] as $flag) {
            $this->assertFalse(config('onboarding_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_public_self_signup_mutation_disabled_by_default(): void
    {
        $this->assertFalse(config('onboarding_governance.public_self_signup_mutation_enabled'));
    }

    public function test_governance_audit_passes(): void
    {
        $signals = app(OnboardingGovernanceAuditService::class)->evaluate();

        foreach ($signals as $signal) {
            $this->assertNotSame(
                OnboardingGovernanceAuditService::STATUS_FAIL,
                $signal['status'],
                $signal['key'].': '.$signal['message'],
            );
        }
    }

    public function test_go_no_go_is_go(): void
    {
        $report = app(OnboardingGoNoGoService::class)->evaluate();

        $this->assertSame(OnboardingGoNoGoService::DECISION_GO, $report['decision']);
    }

    public function test_all_onboarding_commands_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ((array) config('onboarding_governance.onboarding_commands') as $command) {
            $this->assertContains($command, $registered);
        }
    }

    public function test_prior_sprint_gates_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ((array) config('onboarding_governance.required_commands') as $command) {
            $this->assertContains($command, $registered);
        }
    }

    public function test_go_no_go_command_exits_zero(): void
    {
        $this->assertSame(0, Artisan::call('onboarding:go-no-go'));
    }

    public function test_governance_audit_command_exits_zero(): void
    {
        $this->assertSame(0, Artisan::call('onboarding:governance-audit'));
    }

    public function test_start_command_dry_run_does_not_mutate(): void
    {
        $code = Artisan::call('onboarding:start', ['--plan' => 'starter']);

        $this->assertSame(0, $code);
        $this->assertSame(0, \App\Models\TenantProvisioningRun::count());
    }

    public function test_start_command_execute_requires_idempotency_key(): void
    {
        $code = Artisan::call('onboarding:start', ['--execute' => true, '--plan' => 'starter']);

        $this->assertSame(1, $code);
        $this->assertSame(0, \App\Models\Tenant::count());
    }

    public function test_start_command_execute_creates_tenant(): void
    {
        $code = Artisan::call('onboarding:start', [
            '--execute' => true,
            '--idempotency-key' => 'cmd-exec-1',
            '--plan' => 'starter',
            '--tenant-name' => 'CLI Toko',
            '--branch-name' => 'Pusat',
            '--with-cashier' => true,
            '--with-register' => true,
        ]);

        $this->assertSame(0, $code);
        $this->assertSame(1, \App\Models\Tenant::count());
    }

    public function test_command_output_has_no_pii(): void
    {
        Artisan::call('onboarding:start', [
            '--execute' => true,
            '--idempotency-key' => 'cmd-pii-1',
            '--plan' => 'starter',
            '--owner-email' => 'leak@example.com',
            '--owner-phone' => '081999888777',
        ]);

        $output = Artisan::output();

        $this->assertStringNotContainsString('leak@example.com', $output);
        $this->assertStringNotContainsString('081999888777', $output);
    }
}
