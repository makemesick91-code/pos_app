<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Services\UsageLedgerAnomaly\UsageLedgerAnomaly;
use App\Services\UsageLedgerAnomaly\UsageLedgerAnomalyDetector;
use App\Services\UsageLedgerAnomaly\UsageLedgerAnomalySeverity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 28 — the usage-ledger anomaly detector is read-only, catches the required
 * anomaly types, redacts secrets, and supports scoping filters (ULR-R001..R006).
 */
class UsageLedgerAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string,mixed> $attrs */
    private function event(Tenant $tenant, array $attrs = []): TenantUsageEvent
    {
        return TenantUsageEvent::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'event_key' => TenantUsageEvent::EVENT_REPORT_EXPORTED,
            'event_category' => TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            'meter_key' => TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
            'quantity' => 1,
            'occurred_at' => Carbon::create(2026, 7, 15, 10),
            'period_key' => '2026-07',
            'idempotency_key' => 'k-'.uniqid('', true),
            'source' => 'api',
            'request_fingerprint' => 'fp-default',
            'metadata' => ['report_type' => 'daily-sales'],
        ], $attrs));
    }

    private function detector(): UsageLedgerAnomalyDetector
    {
        return app(UsageLedgerAnomalyDetector::class);
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function ofType(array $anomalies, string $type): array
    {
        return array_values(array_filter($anomalies, fn (UsageLedgerAnomaly $a) => $a->type === $type));
    }

    public function test_detects_duplicate_fingerprint_double_count(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['idempotency_key' => 'k1', 'request_fingerprint' => 'dupe-fp']);
        $this->event($tenant, ['idempotency_key' => 'k2', 'request_fingerprint' => 'dupe-fp']);

        $dupes = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_DUPLICATE_IDEMPOTENCY);

        $this->assertCount(1, $dupes);
        $this->assertSame(UsageLedgerAnomalySeverity::CRITICAL, $dupes[0]->severity);
        $this->assertTrue($dupes[0]->autoRepairable);
        $this->assertSame(-1, $dupes[0]->quantityDelta);
    }

    public function test_detects_missing_required_field(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['event_key' => '']);

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_MISSING_REQUIRED_FIELD);

        $this->assertNotEmpty($found);
        $this->assertContains('event_key', $found[0]->context['missing']);
        $this->assertFalse($found[0]->autoRepairable);
    }

    public function test_detects_invalid_quantity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['quantity' => 0]);

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_INVALID_QUANTITY);

        $this->assertNotEmpty($found);
        $this->assertSame(0, $found[0]->context['quantity']);
    }

    public function test_detects_invalid_monthly_period_key_format(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['period_key' => '2026-7']); // single-digit month

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_INVALID_PERIOD);

        $this->assertNotEmpty($found);
    }

    public function test_detects_occurred_at_period_key_mismatch(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, [
            'occurred_at' => Carbon::create(2026, 2, 1, 9),
            'period_key' => '2026-07',
        ]);

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_INVALID_PERIOD);

        $this->assertNotEmpty($found);
    }

    public function test_detects_unknown_meter_key(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['meter_key' => 'totally.unknown.meter']);

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_UNKNOWN_METER);

        $this->assertNotEmpty($found);
        $this->assertSame('totally.unknown.meter', $found[0]->meterKey);
    }

    public function test_detects_suspicious_metadata_without_leaking_value(): void
    {
        $tenant = Tenant::factory()->create();
        $secret = 'sk_live_TOPSECRET_VALUE';
        $this->event($tenant, ['metadata' => ['report_type' => 'daily', 'api_token' => $secret]]);

        $found = $this->ofType($this->detector()->scan(), UsageLedgerAnomaly::TYPE_UNSANITIZED_METADATA);

        $this->assertNotEmpty($found);
        $this->assertContains('api_token', $found[0]->context['offending_keys']);
        // The raw secret value must never appear anywhere in the anomaly output.
        $serialized = json_encode($found[0]->toArray());
        $this->assertStringNotContainsString($secret, (string) $serialized);
        $this->assertStringNotContainsString($secret, $found[0]->summary);
    }

    public function test_detector_is_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $this->event($tenant, ['idempotency_key' => 'k1', 'request_fingerprint' => 'dupe-fp']);
        $this->event($tenant, ['idempotency_key' => 'k2', 'request_fingerprint' => 'dupe-fp']);

        $before = TenantUsageEvent::query()->count();
        $this->detector()->scan();
        $this->assertSame($before, TenantUsageEvent::query()->count());
    }

    public function test_scan_supports_tenant_meter_and_severity_filters(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $this->event($a, ['meter_key' => 'totally.unknown.meter']); // warning
        $this->event($b, ['metadata' => ['secret' => 'x']]);        // critical

        $this->assertNotEmpty($this->detector()->scan((int) $a->id));
        $this->assertEmpty($this->detector()->scan((int) $a->id, 'reports.exports.monthly'));

        $criticalOnly = $this->detector()->scan(null, null, UsageLedgerAnomalySeverity::CRITICAL);
        foreach ($criticalOnly as $anomaly) {
            $this->assertSame(UsageLedgerAnomalySeverity::CRITICAL, $anomaly->severity);
        }
    }
}
