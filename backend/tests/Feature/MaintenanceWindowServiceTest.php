<?php

namespace Tests\Feature;

use App\Models\ProductionMaintenanceWindow;
use App\Services\Operations\MaintenanceWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceWindowServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MaintenanceWindowService
    {
        return app(MaintenanceWindowService::class);
    }

    private function attrs(array $overrides = []): array
    {
        return array_merge([
            'title' => 'DB migration',
            'scheduled_start_at' => Carbon::now()->addDay(),
            'scheduled_end_at' => Carbon::now()->addDay()->addHours(2),
            'risk_level' => 'LOW',
        ], $overrides);
    }

    public function test_no_active_windows_is_go(): void
    {
        $this->assertSame(MaintenanceWindowService::DECISION_GO, $this->service()->summary()['decision']);
    }

    public function test_high_risk_without_rollback_forces_no_go(): void
    {
        $this->service()->create($this->attrs(['risk_level' => 'HIGH']));

        $this->assertSame(MaintenanceWindowService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_high_risk_with_rollback_is_watch(): void
    {
        $this->service()->create($this->attrs([
            'risk_level' => 'CRITICAL',
            'rollback_plan_reference' => 'docs/operations/release-rollback-governance.md',
        ]));

        $this->assertSame(MaintenanceWindowService::DECISION_WATCH, $this->service()->summary()['decision']);
    }

    public function test_status_transition_stamps_actual_timestamps(): void
    {
        $window = $this->service()->create($this->attrs());
        $window = $this->service()->transitionStatus($window, ProductionMaintenanceWindow::STATUS_IN_PROGRESS);
        $this->assertNotNull($window->actual_start_at);

        $window = $this->service()->transitionStatus($window, ProductionMaintenanceWindow::STATUS_COMPLETED);
        $this->assertNotNull($window->actual_end_at);
    }

    public function test_secret_metadata_is_redacted(): void
    {
        $window = $this->service()->create($this->attrs(['metadata' => ['api_key' => 'live_secret']]));

        $this->assertSame('[REDACTED]', $window->metadata['api_key']);
    }
}
