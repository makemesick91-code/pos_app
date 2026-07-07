<?php

namespace Tests\Feature;

use App\Models\PilotClosureRun;
use App\Models\User;
use App\Services\Handover\PilotClosureService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotClosureServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PilotClosureService
    {
        return app(PilotClosureService::class);
    }

    private function defect(array $overrides = []): void
    {
        app(PilotDefectService::class)->create(array_merge([
            'title' => 'x', 'area' => 'SYNC', 'severity' => 'MINOR',
        ], $overrides));
    }

    public function test_can_create_closure_run_with_summaries(): void
    {
        $run = $this->service()->create(['closure_reference' => 'CLO-TEST-1'], User::factory()->platformAdmin()->create());

        $this->assertDatabaseHas('pilot_closure_runs', ['closure_reference' => 'CLO-TEST-1']);
        $this->assertSame(PilotClosureRun::STATUS_REVIEW, $run->status);
        $this->assertArrayHasKey('counts', $run->final_defect_summary);
        $this->assertArrayHasKey('decision', $run->accepted_risk_summary);
    }

    public function test_open_blocking_defect_causes_no_go(): void
    {
        $this->defect(['severity' => 'BLOCKER']);

        $this->assertSame(PilotClosureService::DECISION_NO_GO, $this->service()->evaluate()['decision']);
    }

    public function test_open_major_defect_causes_watch(): void
    {
        $this->defect(['severity' => 'MAJOR']);

        $this->assertSame(PilotClosureService::DECISION_WATCH, $this->service()->evaluate()['decision']);
    }

    public function test_no_defects_is_go(): void
    {
        $this->assertSame(PilotClosureService::DECISION_GO, $this->service()->evaluate()['decision']);
    }

    public function test_approved_closure_stores_approver_and_timestamp(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $run = $this->service()->create([], $admin);

        $run = $this->service()->approve($run, $admin);

        $this->assertSame(PilotClosureRun::STATUS_APPROVED, $run->status);
        $this->assertSame($admin->id, $run->approved_by);
        $this->assertNotNull($run->approved_at);
    }

    public function test_output_does_not_expose_secrets(): void
    {
        $this->defect(['metadata' => ['api_key' => 'sk_live_secret']]);

        $json = json_encode($this->service()->evaluate());

        $this->assertStringNotContainsString('sk_live_secret', (string) $json);
    }
}
