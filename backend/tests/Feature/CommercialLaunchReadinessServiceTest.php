<?php

namespace Tests\Feature;

use App\Models\CommercialLaunchRun;
use App\Models\CommercialLaunchSignoff;
use App\Models\SaasPackageCatalog;
use App\Services\Commercial\CommercialLaunchReadinessService;
use App\Services\Commercial\SaaSPackageCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLaunchReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CommercialLaunchReadinessService
    {
        return app(CommercialLaunchReadinessService::class);
    }

    public function test_no_active_package_forces_no_go(): void
    {
        $report = $this->service()->evaluate();
        $this->assertSame(CommercialLaunchReadinessService::DECISION_NO_GO, $report['decision']);
        $this->assertSame('NO_GO', $report['package_catalog']['decision']);
    }

    public function test_commercial_docs_signal_passes(): void
    {
        $signals = collect($this->service()->evaluate()['signals']);
        $docs = $signals->firstWhere('key', 'commercial_docs');
        $this->assertSame('PASS', $docs['status']);
    }

    public function test_signoff_summary_flags_rejected_as_no_go(): void
    {
        $run = CommercialLaunchRun::query()->create([
            'launch_reference' => 'LAUNCH-TEST-1',
            'status' => CommercialLaunchRun::STATUS_REVIEW,
        ]);
        $run->signoffs()->create([
            'signoff_reference' => 'SIGN-TEST-1',
            'signer_role' => CommercialLaunchSignoff::ROLE_OWNER,
            'decision' => CommercialLaunchSignoff::DECISION_REJECTED,
        ]);

        $this->assertSame(CommercialLaunchReadinessService::DECISION_NO_GO, $this->service()->signoffSummary()['decision']);
    }

    public function test_create_run_persists_decision_and_summaries(): void
    {
        // Make package catalog GO-ish so the run captures a real evaluation.
        $packages = app(SaaSPackageCatalogService::class);
        $packages->approve($packages->create([
            'name' => 'UMKM',
            'target_segment' => SaasPackageCatalog::SEGMENT_GENERAL_UMKM,
            'monthly_price' => 99000,
            'device_limit' => 2,
        ]));

        $run = $this->service()->createRun(['launch_reference' => 'LAUNCH-TEST-2']);

        $this->assertSame(CommercialLaunchRun::STATUS_REVIEW, $run->status);
        $this->assertContains($run->decision, CommercialLaunchRun::DECISIONS);
        $this->assertNotNull($run->package_summary);
        $this->assertNotNull($run->signoff_summary);
    }
}
