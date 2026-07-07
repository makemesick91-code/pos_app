<?php

namespace Tests\Feature;

use App\Services\Operations\ReleaseRollbackGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseRollbackGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_doc_present_is_go(): void
    {
        $report = app(ReleaseRollbackGovernanceService::class)->evaluate();

        $this->assertSame(ReleaseRollbackGovernanceService::DECISION_GO, $report['decision']);
    }

    public function test_report_checks_required_sections(): void
    {
        $report = app(ReleaseRollbackGovernanceService::class)->evaluate();
        $keys = array_column($report['checks'], 'key');

        $this->assertContains('governance_doc', $keys);
        $this->assertContains('required_sections', $keys);
    }
}
