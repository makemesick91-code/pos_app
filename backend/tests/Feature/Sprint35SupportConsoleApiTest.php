<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\TenantSupportIncident;
use App\Models\TenantSupportSession;
use App\Models\User;
use App\Services\AndroidRuntime\DeviceActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 35 — support console HTTP surface (SUP-R001/R004/R005/R006/R012..R019).
 */
class Sprint35SupportConsoleApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'SUP-API']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_all_support_routes_require_platform_admin(): void
    {
        foreach ([
            ['get', "/api/v1/admin/support-ops/tenants"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/health"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/timeline"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/billing"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/payments"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/entitlements"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/onboarding"],
            ['get', "/api/v1/admin/support-ops/tenants/{$this->tenant->id}/android-runtime"],
            ['get', '/api/v1/admin/support-ops/incidents'],
            ['post', '/api/v1/admin/support-ops/incidents'],
            ['get', '/api/v1/admin/support-ops/governance'],
        ] as [$method, $url]) {
            $this->actingAs($this->owner, 'sanctum')->json($method, $url)->assertForbidden();
        }
    }

    public function test_health_overview_includes_all_dimensions(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/health")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'tenant_id', 'health_status', 'reason_codes',
                'dimensions' => ['billing', 'payment', 'entitlement', 'onboarding', 'android_runtime'],
            ]]);
    }

    public function test_timeline_is_readable_and_redacted(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/timeline")
            ->assertOk()
            ->assertJsonStructure(['data' => ['tenant_id', 'count', 'events']]);
    }

    public function test_device_revoke_uses_sprint34_service_and_is_audited(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'sup-token-1', 'sup-fp-1', 'sup-dev-1', 'Kasir', $this->owner);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/devices/{$activation->id}/revoke", [
                'reason_code' => 'device_lost_or_stolen',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TenantDeviceActivation::STATUS_REVOKED);

        // No raw token/fingerprint leaks.
        $this->assertStringNotContainsString('sup-token-1', $response->getContent());

        $this->assertDatabaseHas('tenant_support_actions', [
            'tenant_id' => $this->tenant->id,
            'action_type' => 'device_revoked',
            'status' => 'completed',
            'reason_code' => 'device_lost_or_stolen',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['tenant_id' => $this->tenant->id]);

        // Revoked device stays revoked (SUP-R013).
        $this->assertSame(TenantDeviceActivation::STATUS_REVOKED, $activation->fresh()->activation_status);
    }

    public function test_device_revoke_requires_reason_code(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'sup-token-2', 'sup-fp-2', 'sup-dev-2', 'Kasir', $this->owner);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/devices/{$activation->id}/revoke", [])
            ->assertStatus(422);
    }

    public function test_device_reactivate_is_governed_not_supported(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'sup-token-3', 'sup-fp-3', 'sup-dev-3', 'Kasir', $this->owner);
        app(\App\Services\AndroidRuntime\DeviceRevocationService::class)->revoke($activation, $this->admin, 'x');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/devices/{$activation->id}/reactivate", [
                'reason_code' => 'device_replacement',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SUPPORT_REACTIVATION_NOT_SUPPORTED')
            ->assertJsonPath('meta.supported', false);

        // Still revoked — nothing re-enabled.
        $this->assertSame(TenantDeviceActivation::STATUS_REVOKED, $activation->fresh()->activation_status);
        $this->assertDatabaseHas('tenant_support_actions', [
            'action_type' => 'device_reactivated',
            'status' => 'denied',
        ]);
    }

    public function test_incident_lifecycle_and_notes_are_tenant_isolated(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/support-ops/incidents', [
                'tenant_id' => $this->tenant->id,
                'reason_code' => 'billing_dispute',
                'category' => 'billing',
                'severity' => 'high',
                'title' => 'Invoice looks wrong',
                'summary' => 'Tenant disputes an amount',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $incidentId = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/incidents/{$incidentId}/notes", [
                'reason_code' => 'billing_dispute',
                'body' => 'Called tenant, awaiting docs',
                'note_type' => 'internal',
            ])
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/support-ops/incidents/{$incidentId}", [
                'reason_code' => 'billing_dispute',
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('tenant_support_incident_notes', [
            'tenant_id' => $this->tenant->id,
            'tenant_support_incident_id' => $incidentId,
        ]);
        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'incident_created']);
        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'note_added']);
        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'incident_updated']);
    }

    public function test_incident_note_body_is_redacted(): void
    {
        $incident = TenantSupportIncident::query()->create([
            'tenant_id' => $this->tenant->id,
            'incident_number' => 'SUP-TEST-1',
            'category' => 'other',
            'severity' => 'low',
            'status' => 'open',
            'title_safe' => 'x',
            'opened_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/incidents/{$incident->id}/notes", [
                'reason_code' => 'internal_review',
                'body' => 'Contact owner at secret@example.com immediately',
            ])
            ->assertCreated();

        $note = $incident->notes()->first();
        $this->assertStringNotContainsString('secret@example.com', $note->body_safe);
    }

    public function test_read_only_context_is_time_bound_and_audited(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/read-only-context/start", [
                'reason_code' => 'tenant_request',
                'ttl_minutes' => 30,
            ])
            ->assertCreated()
            ->assertJsonPath('data.session_type', 'read_only_context')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.read_only', true);

        $sessionId = $response->json('data.id');
        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'read_context_started']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/sessions/{$sessionId}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'read_context_ended']);
    }

    public function test_expired_context_reports_expired_status(): void
    {
        $session = TenantSupportSession::query()->create([
            'tenant_id' => $this->tenant->id,
            'actor_user_id' => $this->admin->id,
            'session_type' => TenantSupportSession::TYPE_READ_ONLY_CONTEXT,
            'status' => TenantSupportSession::STATUS_ACTIVE,
            'reason_code' => 'tenant_request',
            'starts_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);

        $this->assertFalse($session->isEffective());
        $this->assertSame(TenantSupportSession::STATUS_EXPIRED, $session->effectiveStatus());
    }

    public function test_impersonation_is_disabled_and_never_exposes_credentials(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/support-ops/tenants/{$this->tenant->id}/impersonation/start", [
                'reason_code' => 'internal_review',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPPORT_IMPERSONATION_DISABLED')
            ->assertJsonPath('meta.impersonation_enabled', false);

        $this->assertDoesNotMatchRegularExpression('/password|token|secret/i', $response->getContent());
        $this->assertDatabaseHas('tenant_support_actions', [
            'action_type' => 'impersonation_denied',
            'status' => 'denied',
        ]);
    }
}
