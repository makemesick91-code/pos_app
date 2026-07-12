<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBillingData;
use Tests\TestCase;

/**
 * UIX-5 — the Owner and Admin billing surfaces never share authorization. An
 * owner session cannot reach admin billing, a platform-admin session cannot
 * reach owner billing, and an API bearer token never authenticates a browser
 * console (UIX5-R004/R005).
 */
class Uix5BillingSurfaceSeparationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBillingData;

    public function test_owner_session_cannot_access_admin_billing(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->makeOwner($tenant);

        $this->actingAs($owner, 'owner')
            ->get('/admin/billing')
            ->assertRedirect('/admin/login');

        $this->actingAs($owner, 'owner')
            ->get('/admin/billing/invoices')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_session_cannot_access_owner_billing(): void
    {
        $admin = $this->makePlatformAdmin();

        $this->actingAs($admin, 'web')
            ->get('/owner/billing')
            ->assertRedirect('/owner/login');
    }

    public function test_api_bearer_token_does_not_authenticate_owner_billing(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->makeOwner($tenant);
        $token = $owner->createToken('device')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'text/html'])
            ->get('/owner/billing')
            ->assertRedirect('/owner/login');
    }

    public function test_api_bearer_token_does_not_authenticate_admin_billing(): void
    {
        $admin = $this->makePlatformAdmin();
        $token = $admin->createToken('device')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'text/html'])
            ->get('/admin/billing')
            ->assertRedirect('/admin/login');
    }
}
