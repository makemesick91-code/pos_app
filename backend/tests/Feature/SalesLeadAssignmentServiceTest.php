<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\SalesLeadAssignment;
use App\Models\User;
use App\Services\SalesPipeline\SalesLeadAssignmentService;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeadAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): SalesLead
    {
        return app(SalesLeadIntakeService::class)->create(['business_name' => 'X']);
    }

    private function service(): SalesLeadAssignmentService
    {
        return app(SalesLeadAssignmentService::class);
    }

    public function test_can_assign_lead(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create();

        $assignment = $this->service()->assign($lead, ['assigned_to_user_id' => $user->id]);

        $this->assertSame(SalesLeadAssignment::STATUS_ACTIVE, $assignment->status);
        $this->assertSame($user->id, $lead->refresh()->assigned_to_user_id);
    }

    public function test_can_reassign_lead_with_history(): void
    {
        $lead = $this->lead();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->service()->assign($lead, ['assigned_to_user_id' => $first->id]);
        $this->service()->assign($lead, ['assigned_to_user_id' => $second->id]);

        $this->assertSame(2, SalesLeadAssignment::query()->count());
        $this->assertSame(1, SalesLeadAssignment::query()->where('status', SalesLeadAssignment::STATUS_REASSIGNED)->count());
        $this->assertSame(1, SalesLeadAssignment::query()->active()->count());
        $this->assertSame($second->id, $lead->refresh()->assigned_to_user_id);
    }

    public function test_can_unassign_lead(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create();

        $this->service()->assign($lead, ['assigned_to_user_id' => $user->id]);
        $this->service()->unassign($lead, null, 'no longer needed');

        $this->assertNull($lead->refresh()->assigned_to_user_id);
        $this->assertSame(0, SalesLeadAssignment::query()->active()->count());
    }

    public function test_active_assignment_summary_works(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create();
        $this->service()->assign($lead, ['assigned_to_user_id' => $user->id]);

        $summary = $this->service()->summary();
        $this->assertSame(1, $summary['active_assignments']);
        $this->assertSame('GO', $summary['decision']);
    }
}
