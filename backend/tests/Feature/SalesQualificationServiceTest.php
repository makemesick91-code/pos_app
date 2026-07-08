<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use App\Services\SalesPipeline\SalesQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesQualificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SalesQualificationService
    {
        return app(SalesQualificationService::class);
    }

    private function richLead(): SalesLead
    {
        return app(SalesLeadIntakeService::class)->create([
            'business_name' => 'Toko Lengkap',
            'contact_name' => 'Budi',
            'contact_email' => 'budi@example.com',
            'business_type' => 'retail',
            'estimated_store_count' => 3,
            'estimated_device_count' => 4,
            'interest_package_code' => 'PRO',
        ]);
    }

    public function test_qualification_score_is_calculated(): void
    {
        $score = $this->service()->score($this->richLead());
        $this->assertGreaterThanOrEqual(60, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_can_mark_qualified(): void
    {
        $lead = $this->service()->markQualified($this->richLead());

        $this->assertSame(SalesLead::STATUS_QUALIFIED, $lead->status);
        $this->assertNotNull($lead->qualified_at);
        $this->assertNotNull($lead->qualification_score);
    }

    public function test_can_mark_lost(): void
    {
        $lead = $this->service()->markLost($this->richLead(), 'budget');

        $this->assertSame(SalesLead::STATUS_LOST, $lead->status);
        $this->assertNotNull($lead->lost_at);
        $this->assertSame('budget', $lead->lost_reason);
    }

    public function test_can_mark_ready_for_onboarding_without_provisioning(): void
    {
        $usersBefore = User::query()->count();

        $lead = $this->service()->markReadyForOnboarding($this->richLead());

        $this->assertSame(SalesLead::STATUS_WON_READY_FOR_ONBOARDING, $lead->status);
        $this->assertNotNull($lead->ready_for_onboarding_at);

        // Never provisions.
        $this->assertSame(0, Tenant::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame(0, DB::table('tenant_subscriptions')->count());
        $this->assertSame(0, DB::table('registered_devices')->count());
    }
}
