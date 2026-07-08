<?php

namespace Tests\Feature;

use App\Models\SaasBillingCycle;
use App\Services\BillingCollection\BillingCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingCycleService
    {
        return app(BillingCycleService::class);
    }

    public function test_can_create_cycle(): void
    {
        $cycle = $this->service()->create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
        ]);

        $this->assertSame(SaasBillingCycle::STATUS_DRAFT, $cycle->status);
        $this->assertNotEmpty($cycle->cycle_reference);
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->create([
            'period_start' => '2026-07-31',
            'period_end' => '2026-07-01',
        ]);
    }

    public function test_can_open_lock_close(): void
    {
        $cycle = $this->service()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-31']);

        $this->assertSame(SaasBillingCycle::STATUS_OPEN, $this->service()->open($cycle)->status);
        $this->assertSame(SaasBillingCycle::STATUS_LOCKED, $this->service()->lock($cycle)->status);
        $this->assertSame(SaasBillingCycle::STATUS_CLOSED, $this->service()->close($cycle)->status);
    }

    public function test_closed_cycle_transition_is_conservative(): void
    {
        $cycle = $this->service()->create(['period_start' => '2026-07-01', 'period_end' => '2026-07-31']);
        $this->service()->open($cycle);
        $this->service()->lock($cycle);
        $this->service()->close($cycle);

        // Cannot re-open a locked/closed cycle back to OPEN from CLOSED.
        $this->expectException(InvalidArgumentException::class);
        $this->service()->open($cycle->fresh());
    }
}
