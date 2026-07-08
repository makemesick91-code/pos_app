<?php

namespace Tests\Feature;

use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\BillingPeriodService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Sprint 30 — the billing period is canonical and deterministic (BIL-R001).
 */
class BillingPeriodTest extends TestCase
{
    private BillingPeriodService $periods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->periods = app(BillingPeriodService::class);
    }

    public function test_period_resolves_stable_key_for_a_date(): void
    {
        $period = $this->periods->resolveForDate(CarbonImmutable::parse('2026-07-15 10:00:00', 'Asia/Jakarta'));

        $this->assertSame('2026-07', $period->key);
        $this->assertSame('2026-07-01', $period->start->format('Y-m-d'));
        $this->assertSame('2026-07-31', $period->end->format('Y-m-d'));
    }

    public function test_due_date_honours_configured_due_days(): void
    {
        config()->set('billing_governance.period.due_days', 10);

        $period = $this->periods->resolveForKey('2026-07');

        $this->assertSame('2026-07-11', $period->dueAt->format('Y-m-d'));
    }

    public function test_period_is_deterministic_regardless_of_instant(): void
    {
        $a = $this->periods->resolveForDate(CarbonImmutable::parse('2026-07-02 00:00:01', 'Asia/Jakarta'));
        $b = $this->periods->resolveForDate(CarbonImmutable::parse('2026-07-28 23:59:59', 'Asia/Jakarta'));

        $this->assertSame($a->key, $b->key);
        $this->assertEquals($a->start, $b->start);
        $this->assertEquals($a->dueAt, $b->dueAt);
    }

    public function test_invalid_period_key_is_rejected(): void
    {
        $this->expectException(BillingGovernanceException::class);
        $this->periods->resolveForKey('2026/07');
    }
}
