<?php

namespace Tests\Feature;

use App\Models\ProductionOperationRun;
use App\Services\Operations\ProductionOperationsHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOperationsHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductionOperationsHealthService
    {
        return app(ProductionOperationsHealthService::class);
    }

    public function test_all_required_signals_are_evaluated(): void
    {
        $signals = $this->service()->signals();
        $keys = array_column($signals, 'key');

        foreach ((array) config('production_operations.required_health_signals') as $required) {
            $this->assertContains($required, $keys, "Signal {$required} should be evaluated.");
        }
    }

    public function test_healthy_environment_is_go(): void
    {
        $this->assertSame(
            ProductionOperationsHealthService::DECISION_GO,
            $this->service()->evaluate()['decision'],
        );
    }

    public function test_critical_fail_forces_no_go(): void
    {
        $decision = $this->service()->decide([
            ['key' => 'backend_health', 'status' => 'FAIL', 'critical' => true],
            ['key' => 'product_sync', 'status' => 'PASS', 'critical' => false],
        ]);

        $this->assertSame(ProductionOperationsHealthService::DECISION_NO_GO, $decision);
    }

    public function test_non_critical_warn_forces_watch(): void
    {
        $decision = $this->service()->decide([
            ['key' => 'backend_health', 'status' => 'PASS', 'critical' => true],
            ['key' => 'product_sync', 'status' => 'WARN', 'critical' => false],
        ]);

        $this->assertSame(ProductionOperationsHealthService::DECISION_WATCH, $decision);
    }

    public function test_create_run_persists_evaluation(): void
    {
        $run = $this->service()->createRun(['metadata' => ['note' => 'baseline']]);

        $this->assertInstanceOf(ProductionOperationRun::class, $run);
        $this->assertSame(ProductionOperationRun::STATUS_REVIEW, $run->status);
        $this->assertNotNull($run->health_signals);
        $this->assertNotNull($run->decision);
    }
}
