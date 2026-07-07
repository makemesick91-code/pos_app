<?php

namespace Tests\Feature;

use App\Models\SaasPackageCatalog;
use App\Services\Commercial\SaaSPackageCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSPackageCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SaaSPackageCatalogService
    {
        return app(SaaSPackageCatalogService::class);
    }

    public function test_no_active_package_is_no_go(): void
    {
        $this->assertSame(SaaSPackageCatalogService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_active_required_segment_package_is_at_least_watch(): void
    {
        $service = $this->service();
        $package = $service->create([
            'name' => 'UMKM Starter',
            'target_segment' => SaasPackageCatalog::SEGMENT_GENERAL_UMKM,
            'monthly_price' => 99000,
            'device_limit' => 2,
        ]);
        $service->approve($package);

        $summary = $service->summary();
        // Required GENERAL_UMKM is covered; recommended segments are not → WATCH.
        $this->assertSame(SaaSPackageCatalogService::DECISION_WATCH, $summary['decision']);
        $this->assertSame(1, $summary['active_total']);
        $this->assertContains(SaasPackageCatalog::SEGMENT_GENERAL_UMKM, $summary['active_segments']);
    }

    public function test_secret_values_are_redacted_from_notes(): void
    {
        $package = $this->service()->create([
            'name' => 'Secret package',
            'target_segment' => SaasPackageCatalog::SEGMENT_WARUNG,
            'commercial_notes' => 'password: hunter2 internal',
            'metadata' => ['api_key' => 'abc123'],
        ]);

        $this->assertStringNotContainsString('hunter2', (string) $package->commercial_notes);
        $this->assertSame('[REDACTED]', $package->metadata['api_key']);
    }

    public function test_retire_moves_package_out_of_active(): void
    {
        $service = $this->service();
        $package = $service->create([
            'name' => 'Retire me',
            'target_segment' => SaasPackageCatalog::SEGMENT_GENERAL_UMKM,
        ]);
        $service->approve($package);
        $service->retire($package);

        $this->assertSame(SaasPackageCatalog::STATUS_RETIRED, $package->fresh()->status);
        $this->assertSame(0, $service->summary()['active_total']);
    }
}
