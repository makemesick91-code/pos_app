<?php

namespace Tests\Feature;

use App\Services\Operations\BackupRestoreGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRestoreGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_doc_present_is_go(): void
    {
        $report = app(BackupRestoreGovernanceService::class)->evaluate();

        $this->assertSame(BackupRestoreGovernanceService::DECISION_GO, $report['decision']);
    }

    public function test_report_contains_required_section_check(): void
    {
        $report = app(BackupRestoreGovernanceService::class)->evaluate();
        $keys = array_column($report['checks'], 'key');

        $this->assertContains('governance_doc', $keys);
        $this->assertContains('required_sections', $keys);
    }
}
