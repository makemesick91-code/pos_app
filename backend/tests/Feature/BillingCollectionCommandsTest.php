<?php

namespace Tests\Feature;

use App\Services\BillingCollection\BillingCollectionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingCollectionCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function runJson(string $command): array
    {
        Artisan::call($command, ['--json' => true]);
        $decoded = json_decode(trim(Artisan::output()), true);
        $this->assertIsArray($decoded, "{$command} --json must emit valid JSON.");

        return $decoded;
    }

    public function test_readiness_json_is_valid(): void
    {
        $report = $this->runJson('billing-collection:readiness');
        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('signals', $report);
    }

    public function test_invoice_summary_json_is_valid(): void
    {
        $report = $this->runJson('billing-collection:invoice-summary');
        $this->assertArrayHasKey('total_invoices', $report);
        $this->assertSame('GO', $report['decision']);
    }

    public function test_collection_summary_json_is_valid(): void
    {
        $report = $this->runJson('billing-collection:collection-summary');
        $this->assertArrayHasKey('activities_by_type', $report);
        $this->assertTrue($report['no_real_sending']);
    }

    public function test_go_no_go_json_is_valid(): void
    {
        $report = $this->runJson('billing-collection:go-no-go');
        $this->assertArrayHasKey('decision', $report);
        $this->assertArrayHasKey('gates', $report);
    }

    public function test_strict_mode_fails_on_watch_or_no_go(): void
    {
        // Fresh DB has no signoffs → readiness is WATCH; --strict must fail (exit 1).
        $exit = Artisan::call('billing-collection:readiness', ['--json' => true, '--strict' => true]);
        $this->assertSame(1, $exit);
    }

    public function test_go_no_go_go_when_signoffs_present(): void
    {
        $readiness = app(BillingCollectionReadinessService::class);
        foreach ((array) config('billing_collection.required_signoff_roles') as $role) {
            $readiness->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }

        $exit = Artisan::call('billing-collection:go-no-go', ['--json' => true, '--strict' => true]);
        $this->assertSame(0, $exit);
    }

    public function test_output_does_not_expose_secrets(): void
    {
        app(BillingCollectionReadinessService::class)->addSignoff([
            'signer_role' => 'OWNER', 'decision' => 'APPROVED', 'notes' => 'token: ghp_secretvalue',
        ]);

        Artisan::call('billing-collection:readiness', ['--json' => true]);
        $this->assertStringNotContainsString('ghp_secretvalue', Artisan::output());
    }
}
