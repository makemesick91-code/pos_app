<?php

namespace Tests\Feature;

use App\Models\LeadInterestSubmission;
use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PublicWebsite\LeadInterestGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LeadInterestGovernanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LeadInterestGovernanceService
    {
        return app(LeadInterestGovernanceService::class);
    }

    public function test_submit_requires_consent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->submit(['contact_name' => 'Budi', 'contact_email' => 'b@example.com']);
    }

    public function test_submit_is_interest_only_and_never_provisions(): void
    {
        $tenantsBefore = Tenant::query()->count();
        $usersBefore = User::query()->count();
        $devicesBefore = RegisteredDevice::query()->count();

        $lead = $this->service()->submit([
            'contact_name' => 'Budi', 'contact_email' => 'b@example.com',
            'business_name' => 'Warung Budi', 'consent' => true,
        ]);

        $this->assertSame(LeadInterestSubmission::STATUS_NEW, $lead->status);
        $this->assertNotNull($lead->consent_accepted_at);
        $this->assertSame($tenantsBefore, Tenant::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($devicesBefore, RegisteredDevice::query()->count());
    }

    public function test_submit_sanitizes_secret_like_message(): void
    {
        $lead = $this->service()->submit([
            'contact_name' => 'X', 'contact_email' => 'x@example.com',
            'message' => 'api_key: sk_live_abc123', 'consent' => true,
        ]);

        $this->assertStringNotContainsString('sk_live_abc123', (string) $lead->message);
    }

    public function test_summary_is_interest_only(): void
    {
        $this->service()->submit(['contact_name' => 'X', 'contact_email' => 'x@example.com', 'consent' => true]);

        $summary = $this->service()->summary();
        $this->assertTrue($summary['interest_only']);
        $this->assertSame(LeadInterestGovernanceService::DECISION_GO, $summary['decision']);
        $this->assertSame(1, $summary['counts']['new']);
    }
}
