<?php

namespace Tests\Feature;

use App\Models\ProductionIncident;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\Operations\ProductionIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionIncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductionIncidentService
    {
        return app(ProductionIncidentService::class);
    }

    public function test_create_computes_sla_due_from_severity(): void
    {
        $now = Carbon::parse('2026-07-07 10:00:00');
        $incident = $this->service()->create([
            'title' => 'API down', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL_TENANTS',
        ], null, $now);

        // P0 SLA is 4 hours from detection.
        $this->assertSame('2026-07-07 14:00:00', $incident->sla_due_at->format('Y-m-d H:i:s'));
    }

    public function test_store_must_belong_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenantB->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->create([
            'title' => 'x', 'area' => 'CASHIER', 'severity' => 'P2', 'impact' => 'ONE_STORE',
            'tenant_id' => $tenantA->id, 'store_id' => $store->id,
        ]);
    }

    public function test_open_p0_forces_no_go(): void
    {
        $this->service()->create(['title' => 'x', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL']);

        $this->assertSame(ProductionIncidentService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_open_p2_forces_watch(): void
    {
        $this->service()->create(['title' => 'x', 'area' => 'REPORTING', 'severity' => 'P2', 'impact' => 'DEGRADED']);

        $this->assertSame(ProductionIncidentService::DECISION_WATCH, $this->service()->summary()['decision']);
    }

    public function test_p3_only_is_go(): void
    {
        $this->service()->create(['title' => 'x', 'area' => 'OTHER', 'severity' => 'P3', 'impact' => 'MINOR']);

        $this->assertSame(ProductionIncidentService::DECISION_GO, $this->service()->summary()['decision']);
    }

    public function test_accept_risk_requires_expiry_and_approver_for_blocking(): void
    {
        $incident = $this->service()->create(['title' => 'x', 'area' => 'AUTH', 'severity' => 'P1', 'impact' => 'MANY']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->acceptRisk($incident, ['reason' => 'known upstream issue']);
    }

    public function test_valid_accepted_risk_clears_no_go(): void
    {
        $approver = \App\Models\User::factory()->platformAdmin()->create();
        $incident = $this->service()->create(['title' => 'x', 'area' => 'AUTH', 'severity' => 'P1', 'impact' => 'MANY']);
        $this->service()->acceptRisk($incident, [
            'reason' => 'known upstream issue',
            'approver' => $approver->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // Accepted-risk incidents are no longer "open", so the register clears.
        $this->assertSame(ProductionIncidentService::DECISION_GO, $this->service()->summary()['decision']);
        $this->assertSame('P1', $incident->refresh()->severity, 'Original severity must be preserved.');
    }

    public function test_expired_blocking_accepted_risk_forces_no_go(): void
    {
        $incident = $this->service()->create(['title' => 'x', 'area' => 'AUTH', 'severity' => 'P0', 'impact' => 'ALL']);
        $incident->update([
            'status' => ProductionIncident::STATUS_ACCEPTED_RISK,
            'accepted_risk_at' => Carbon::now()->subDays(10),
            'accepted_risk_expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertSame(ProductionIncidentService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_sla_breach_detection_stamps_open_overdue(): void
    {
        $incident = $this->service()->create([
            'title' => 'x', 'area' => 'BACKEND_API', 'severity' => 'P0', 'impact' => 'ALL',
        ], null, Carbon::now()->subDays(2));

        $count = $this->service()->detectSlaBreaches();

        $this->assertSame(1, $count);
        $this->assertNotNull($incident->refresh()->sla_breached_at);
    }

    public function test_secret_values_are_redacted(): void
    {
        $incident = $this->service()->create([
            'title' => 'x', 'area' => 'OTHER', 'severity' => 'P4', 'impact' => 'LOW',
            'description' => 'token=abc123secret detail',
            'metadata' => ['password' => 'hunter2'],
        ]);

        $this->assertStringNotContainsString('abc123secret', (string) $incident->description);
        $this->assertSame('[REDACTED]', $incident->metadata['password']);
    }
}
