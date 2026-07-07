<?php

namespace Tests\Feature;

use App\Models\SaasPackageCatalog;
use App\Services\Commercial\SaaSPackageCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialLaunchCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_summary_command_runs_json(): void
    {
        $this->artisan('commercial:package-summary --json')->assertExitCode(1); // no active package → NO_GO
    }

    public function test_onboarding_capacity_command_is_go(): void
    {
        $this->artisan('commercial:onboarding-capacity --json')->assertExitCode(0);
    }

    public function test_launch_readiness_command_runs(): void
    {
        // No active package → NO_GO → exit 1.
        $this->artisan('commercial:launch-readiness')->assertExitCode(1);
    }

    public function test_launch_go_no_go_command_runs(): void
    {
        $this->artisan('commercial:launch-go-no-go --json')->assertExitCode(1);
    }

    public function test_package_summary_go_when_all_segments_active(): void
    {
        $packages = app(SaaSPackageCatalogService::class);
        foreach ([
            SaasPackageCatalog::SEGMENT_GENERAL_UMKM,
            SaasPackageCatalog::SEGMENT_WARUNG,
            SaasPackageCatalog::SEGMENT_TOKO_KECIL,
            SaasPackageCatalog::SEGMENT_KEDAI,
            SaasPackageCatalog::SEGMENT_LAUNDRY,
            SaasPackageCatalog::SEGMENT_RETAIL,
            SaasPackageCatalog::SEGMENT_APOTEK_LIGHT,
        ] as $segment) {
            $packages->approve($packages->create([
                'name' => "Pkg {$segment}",
                'target_segment' => $segment,
                'monthly_price' => 50000,
                'device_limit' => 1,
            ]));
        }

        $this->artisan('commercial:package-summary --strict')->assertExitCode(0);
    }
}
