<?php

namespace Tests\Feature;

use App\Models\LeadInterestSubmission;
use App\Models\RegisteredDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsitePublicRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_without_auth(): void
    {
        $this->get('/')->assertOk()->assertSee('Aish POS Lite');
        $this->get('/packages')->assertOk();
        $this->get('/privacy')->assertOk()->assertSee('Kebijakan Privasi');
        $this->get('/terms')->assertOk()->assertSee('Ketentuan Layanan');
        $this->get('/thank-you')->assertOk();
    }

    public function test_interest_submission_stores_lead_and_never_provisions(): void
    {
        $tenants = Tenant::query()->count();
        $users = User::query()->count();
        $devices = RegisteredDevice::query()->count();

        $this->post('/interest', [
            'contact_name' => 'Budi', 'contact_email' => 'budi@example.com',
            'business_name' => 'Warung Budi', 'consent' => '1',
        ])->assertRedirect('/thank-you');

        $this->assertSame(1, LeadInterestSubmission::query()->count());
        $this->assertSame($tenants, Tenant::query()->count());
        $this->assertSame($users, User::query()->count());
        $this->assertSame($devices, RegisteredDevice::query()->count());
    }

    public function test_interest_submission_requires_consent(): void
    {
        $this->from('/')->post('/interest', [
            'contact_name' => 'Budi', 'contact_email' => 'budi@example.com',
        ])->assertSessionHasErrors('consent');

        $this->assertSame(0, LeadInterestSubmission::query()->count());
    }

    public function test_interest_submission_validates_email(): void
    {
        $this->from('/')->post('/interest', [
            'contact_name' => 'Budi', 'contact_email' => 'not-an-email', 'consent' => '1',
        ])->assertSessionHasErrors('contact_email');
    }

    public function test_public_pages_do_not_leak_admin_paths(): void
    {
        $this->get('/')->assertDontSee('/api/v1/admin');
    }
}
