<?php

namespace Tests\Feature;

use App\Services\Commercial\CommercialLaunchGoNoGoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLaunchGoNoGoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CommercialLaunchGoNoGoService
    {
        return app(CommercialLaunchGoNoGoService::class);
    }

    public function test_prior_sprint_gates_are_registered(): void
    {
        $report = $this->service()->evaluate();

        foreach ($report['gates'] as $gate => $ok) {
            $this->assertTrue($ok, "Gate {$gate} should be registered");
        }
    }

    public function test_required_commands_and_docs_signals_pass(): void
    {
        $signals = collect($this->service()->evaluate()['signals']);
        $this->assertSame('PASS', $signals->firstWhere('key', 'required_commands')['status']);
        $this->assertSame('PASS', $signals->firstWhere('key', 'commercial_docs')['status']);
        $this->assertSame('PASS', $signals->firstWhere('key', 'android_release_readiness')['status']);
    }

    public function test_no_active_package_forces_no_go(): void
    {
        $this->assertSame(CommercialLaunchGoNoGoService::DECISION_NO_GO, $this->service()->evaluate()['decision']);
    }
}
