<?php

namespace Tests\Feature;

use App\Models\ObservabilityAlertSuggestion;
use App\Models\ObservabilityAnomalyEvent;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 36 — observability HTTP surface (OBS-R001/R002/R003/R005/R010/R028).
 */
class Sprint36ObservabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'OBS-API']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_public_liveness_is_minimal_and_secret_free(): void
    {
        $response = $this->getJson('/health/live')
            ->assertOk()
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJsonPath('status', 'ok');

        $body = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/password|secret|token|tenant|api_key|:memory:/i', $body);
    }

    public function test_public_readiness_is_minimal_and_no_tenant_or_secret(): void
    {
        $response = $this->getJson('/health/ready')->assertOk()->assertJsonStructure(['status', 'timestamp']);
        $body = $response->getContent();
        // Readiness never leaks component internals, tenant data, or secrets.
        $this->assertDoesNotMatchRegularExpression('/password|secret|token|tenant|components|database|:memory:/i', $body);
    }

    public function test_all_admin_observability_routes_require_platform_admin(): void
    {
        foreach ([
            ['get', '/api/v1/admin/observability/health'],
            ['get', '/api/v1/admin/observability/health/infrastructure'],
            ['get', '/api/v1/admin/observability/health/tenants'],
            ['get', "/api/v1/admin/observability/health/tenants/{$this->tenant->id}"],
            ['get', '/api/v1/admin/observability/queues'],
            ['get', '/api/v1/admin/observability/failed-jobs'],
            ['get', '/api/v1/admin/observability/scheduler'],
            ['get', '/api/v1/admin/observability/anomalies'],
            ['get', '/api/v1/admin/observability/metrics'],
            ['get', '/api/v1/admin/observability/alerts/suggestions'],
            ['get', '/api/v1/admin/observability/governance'],
        ] as [$method, $url]) {
            $this->actingAs($this->owner, 'sanctum')->json($method, $url)->assertForbidden();
        }
    }

    public function test_admin_health_overview_ok(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/observability/health')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'reason_codes', 'components' => ['infrastructure', 'queue', 'scheduler']]]);
    }

    public function test_admin_infrastructure_is_credential_free(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/observability/health/infrastructure')
            ->assertOk();
        $this->assertDoesNotMatchRegularExpression('/password|secret|:memory:|DB_PASSWORD/i', $response->getContent());
    }

    public function test_admin_tenant_probe_ok(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/observability/health/tenants/{$this->tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $this->tenant->id)
            ->assertJsonPath('data.source', 'sprint35_support_tenant_health');
    }

    public function test_admin_queue_scheduler_metrics_governance_ok(): void
    {
        foreach (['queues', 'scheduler', 'metrics', 'governance'] as $path) {
            $this->actingAs($this->admin, 'sanctum')
                ->getJson("/api/v1/admin/observability/{$path}")
                ->assertOk();
        }
    }

    public function test_failed_job_retry_is_disabled_by_default(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/observability/failed-jobs/1/retry', ['reason_code' => 'governed_retry'])
            ->assertStatus(409)
            ->assertJsonPath('retry_enabled', false);
    }

    public function test_anomaly_acknowledge_requires_reason_code(): void
    {
        $anomaly = $this->anomaly();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/anomalies/{$anomaly->id}/acknowledge", [])
            ->assertStatus(422);
    }

    public function test_anomaly_acknowledge_and_resolve_are_audited(): void
    {
        $anomaly = $this->anomaly();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/anomalies/{$anomaly->id}/acknowledge", ['reason_code' => 'operator_review'])
            ->assertOk()
            ->assertJsonPath('data.status', ObservabilityAnomalyEvent::STATUS_ACKNOWLEDGED);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/anomalies/{$anomaly->id}/resolve", ['reason_code' => 'resolved_after_fix'])
            ->assertOk()
            ->assertJsonPath('data.status', ObservabilityAnomalyEvent::STATUS_RESOLVED);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'OBSERVABILITY_ANOMALY_ACKNOWLEDGE']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'OBSERVABILITY_ANOMALY_RESOLVE']);
    }

    public function test_alert_suggestion_dismiss_requires_reason_and_is_audited(): void
    {
        $suggestion = $this->suggestion();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/alerts/suggestions/{$suggestion->id}/dismiss", [])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/alerts/suggestions/{$suggestion->id}/dismiss", ['reason_code' => 'false_positive'])
            ->assertOk()
            ->assertJsonPath('data.status', ObservabilityAlertSuggestion::STATUS_DISMISSED);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'OBSERVABILITY_ALERT_SUGGESTION_DISMISS']);
    }

    public function test_alert_suggestion_accept_links_incident_and_is_audited(): void
    {
        $suggestion = $this->suggestion();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/observability/alerts/suggestions/{$suggestion->id}/accept", ['reason_code' => 'incident_linked'])
            ->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'OBSERVABILITY_ALERT_SUGGESTION_ACCEPT']);
        $this->assertDatabaseCount('tenant_support_incidents', 1);
    }

    public function test_anomaly_index_is_redacted(): void
    {
        $this->anomaly();
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/observability/anomalies')
            ->assertOk();
        $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key/i', $response->getContent());
    }

    private function anomaly(): ObservabilityAnomalyEvent
    {
        return ObservabilityAnomalyEvent::query()->create([
            'tenant_id' => $this->tenant->id,
            'anomaly_key' => 'billing.overdue_past_grace',
            'category' => 'billing',
            'severity' => 'high',
            'status' => ObservabilityAnomalyEvent::STATUS_OPEN,
            'reason_code' => 'invoice_overdue_past_grace',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrence_count' => 1,
            'summary_safe' => '1 invoice overdue past grace.',
        ]);
    }

    private function suggestion(): ObservabilityAlertSuggestion
    {
        $anomaly = $this->anomaly();

        return ObservabilityAlertSuggestion::query()->create([
            'tenant_id' => $this->tenant->id,
            'anomaly_event_id' => $anomaly->id,
            'suggested_action' => 'review_billing_anomaly',
            'severity' => 'high',
            'status' => ObservabilityAlertSuggestion::STATUS_SUGGESTED,
            'summary_safe' => 'review billing anomaly',
        ]);
    }
}
