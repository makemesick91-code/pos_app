<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalSignoff;
use App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService;
use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubscriptionRenewalCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function jsonReport(string $command): array
    {
        Artisan::call($command, ['--json' => true]);

        return (array) json_decode(Artisan::output(), true);
    }

    public function test_readiness_json_is_valid(): void
    {
        $report = $this->jsonReport('subscription-renewal:readiness');
        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('signals', $report);
    }

    public function test_candidate_summary_json_is_valid(): void
    {
        $report = $this->jsonReport('subscription-renewal:candidate-summary');
        $this->assertArrayHasKey('total_candidates', $report);
    }

    public function test_dunning_summary_json_is_valid(): void
    {
        $report = $this->jsonReport('subscription-renewal:dunning-summary');
        $this->assertTrue($report['manual_only']);
        $this->assertTrue($report['no_real_sending']);
    }

    public function test_go_no_go_json_is_valid(): void
    {
        $report = $this->jsonReport('subscription-renewal:go-no-go');
        $this->assertArrayHasKey('gates', $report);
        $this->assertArrayHasKey('decision', $report);
    }

    public function test_strict_mode_fails_on_watch_or_no_go(): void
    {
        // Fresh DB has no signoffs → WATCH/NO_GO → strict fails (non-zero).
        $code = Artisan::call('subscription-renewal:readiness', ['--json' => true, '--strict' => true]);
        $this->assertNotSame(0, $code);
    }

    public function test_go_no_go_can_reach_go_and_strict_passes(): void
    {
        app(SubscriptionRenewalPolicyService::class)->ensureDefault();
        $readiness = app(SubscriptionRenewalReadinessService::class);
        foreach ((array) config('subscription_renewal.required_signoff_roles') as $role) {
            $readiness->addSignoff(['signer_role' => $role, 'decision' => SubscriptionRenewalSignoff::DECISION_APPROVED]);
        }

        $code = Artisan::call('subscription-renewal:go-no-go', ['--json' => true, '--strict' => true]);
        $this->assertSame(0, $code);
    }

    public function test_output_does_not_expose_secrets(): void
    {
        Artisan::call('subscription-renewal:readiness', ['--json' => true]);
        $this->assertStringNotContainsString('sk_live', Artisan::output());
    }
}
