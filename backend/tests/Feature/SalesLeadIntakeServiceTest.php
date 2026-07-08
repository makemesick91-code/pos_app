<?php

namespace Tests\Feature;

use App\Models\LeadInterestSubmission;
use App\Models\SalesLead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesLeadIntakeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SalesLeadIntakeService
    {
        return app(SalesLeadIntakeService::class);
    }

    private function submission(array $overrides = []): LeadInterestSubmission
    {
        return LeadInterestSubmission::query()->create(array_merge([
            'lead_reference' => 'IL-'.uniqid(),
            'status' => LeadInterestSubmission::STATUS_NEW,
            'business_name' => 'Toko Maju',
            'contact_name' => 'Budi',
            'contact_email' => 'budi@example.com',
            'contact_phone' => '0811',
            'business_type' => 'retail',
            'estimated_store_count' => 2,
            'estimated_device_count' => 3,
            'interest_package_code' => 'PRO',
            'message' => 'interested',
            'source' => 'public-website',
            'consent_accepted_at' => Carbon::now(),
        ], $overrides));
    }

    public function test_can_create_manual_sales_lead(): void
    {
        $lead = $this->service()->create([
            'business_name' => 'Warung Sederhana',
            'contact_email' => 'x@example.com',
        ]);

        $this->assertDatabaseHas('sales_leads', ['id' => $lead->id, 'source' => 'manual']);
        $this->assertSame(SalesLead::STATUS_NEW, $lead->status);
        $this->assertNotEmpty($lead->lead_reference);
    }

    public function test_can_import_from_lead_interest_submission(): void
    {
        $submission = $this->submission();
        $lead = $this->service()->importFromInterest($submission);

        $this->assertSame($submission->id, $lead->lead_interest_submission_id);
        $this->assertSame('Toko Maju', $lead->business_name);
        $this->assertSame('public-website', $lead->source);
    }

    public function test_duplicate_import_replays_existing_lead(): void
    {
        $submission = $this->submission();
        $first = $this->service()->importFromInterest($submission);
        $second = $this->service()->importFromInterest($submission);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SalesLead::query()->count());
    }

    public function test_secret_like_values_are_redacted(): void
    {
        $lead = $this->service()->create([
            'business_name' => 'note password: hunter2',
            'notes' => 'server_key: sk_live_zzz',
            'metadata' => ['api_key' => 'sk_live_leak', 'ok' => 'value'],
        ]);

        $this->assertStringNotContainsString('hunter2', (string) $lead->business_name);
        $this->assertStringNotContainsString('sk_live_zzz', (string) $lead->notes);
        $this->assertSame('[REDACTED]', $lead->metadata['api_key']);
        $this->assertSame('value', $lead->metadata['ok']);
    }

    public function test_intake_does_not_provision_tenant_user_subscription_device(): void
    {
        $usersBefore = User::query()->count();

        $this->service()->create(['business_name' => 'X']);
        $this->service()->importFromInterest($this->submission());

        $this->assertSame(0, Tenant::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('tenant_subscriptions')->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('registered_devices')->count());
    }
}
