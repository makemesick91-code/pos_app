<?php

namespace Tests\Feature;

use App\Models\ObservabilityAlertSuggestion;
use App\Models\ObservabilityAnomalyEvent;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\TenantEntitlementDecision;
use App\Models\TenantDeviceActivation;
use App\Models\TenantProvisioningRun;
use App\Models\User;
use App\Services\Observability\AndroidSyncAnomalyService;
use App\Services\Observability\BillingPaymentAnomalyService;
use App\Services\Observability\EntitlementAnomalyService;
use App\Services\Observability\ExportReportAnomalyService;
use App\Services\Observability\ObservabilityAnomalyScanService;
use App\Services\Observability\ObservabilityIncidentSuggestionService;
use App\Services\Observability\OnboardingAnomalyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 36 — anomaly detection + suggestion (OBS-R013..R019/R029). All detectors
 * are read-only and source from Sprint 30–35 ledgers; a scan mutates no domain state.
 */
class Sprint36ObservabilityAnomalyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'OBS-ANOM']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        User::factory()->create(['tenant_id' => $this->tenant->id, 'store_id' => $store->id, 'role' => User::ROLE_TENANT_OWNER]);
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_android_sync_repeated_failures_detected(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->syncBatch(TenantAndroidSyncBatch::STATUS_FAILED, $i);
        }
        $anomalies = app(AndroidSyncAnomalyService::class)->detect();
        $keys = array_column($anomalies, 'anomaly_key');
        $this->assertContains('android_sync.failed_batches', $keys);
    }

    public function test_revoked_device_attempt_detected(): void
    {
        $this->syncBatch(TenantAndroidSyncBatch::STATUS_FAILED, 1);
        TenantDeviceActivation::query()->create([
            'tenant_id' => $this->tenant->id,
            'activation_status' => TenantDeviceActivation::STATUS_REVOKED,
        ]);
        $anomalies = app(AndroidSyncAnomalyService::class)->detect();
        $this->assertContains('android_sync.revoked_device_attempt', array_column($anomalies, 'anomaly_key'));
    }

    public function test_duplicate_replay_spike_detected(): void
    {
        $batch = $this->syncBatch(TenantAndroidSyncBatch::STATUS_COMPLETED, 99);
        for ($i = 0; $i < 25; $i++) {
            TenantAndroidSyncItem::query()->create([
                'sync_batch_id' => $batch->id,
                'tenant_id' => $this->tenant->id,
                'client_item_id' => 'ci-'.$i,
                'item_type' => TenantAndroidSyncItem::TYPE_SALE,
                'action' => 'create',
                'status' => TenantAndroidSyncItem::STATUS_DUPLICATE,
            ]);
        }
        $keys = array_column(app(AndroidSyncAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('android_sync.duplicate_spike', $keys);
    }

    public function test_overdue_invoice_past_grace_detected(): void
    {
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);
        $keys = array_column(app(BillingPaymentAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('billing.overdue_past_grace', $keys);
    }

    public function test_repeated_failed_payments_detected(): void
    {
        $invoice = TenantBillingInvoice::factory()->create(['tenant_id' => $this->tenant->id]);
        for ($i = 0; $i < 4; $i++) {
            TenantBillingPayment::query()->create([
                'tenant_id' => $this->tenant->id,
                'invoice_id' => $invoice->id,
                'payment_reference' => 'pay-ref-'.$i,
                'idempotency_key' => 'pay-idem-'.$i,
                'amount' => 99000,
                'status' => TenantBillingPayment::STATUS_FAILED,
            ]);
        }
        $keys = array_column(app(BillingPaymentAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('payment.repeated_failed', $keys);
    }

    public function test_webhook_invalid_signature_spike_detected_app_level(): void
    {
        for ($i = 0; $i < 4; $i++) {
            TenantBillingGatewayEvent::query()->create([
                'provider' => 'mock_qris',
                'payload_hash' => hash('sha256', 'evt-'.$i),
                'status' => TenantBillingGatewayEvent::STATUS_REJECTED,
            ]);
        }
        $anomalies = app(BillingPaymentAnomalyService::class)->detect();
        $webhook = collect($anomalies)->firstWhere('anomaly_key', 'payment.webhook_rejected_spike');
        $this->assertNotNull($webhook);
        $this->assertNull($webhook['tenant_id']);
    }

    public function test_payment_intent_stuck_pending_detected(): void
    {
        $invoice = TenantBillingInvoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $intent = TenantBillingPaymentIntent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'status' => TenantBillingPaymentIntent::STATUS_PENDING,
        ]);
        $intent->created_at = now()->subHours(5);
        $intent->save();

        $keys = array_column(app(BillingPaymentAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('payment.intent_stuck_pending', $keys);
    }

    public function test_entitlement_denial_spike_detected(): void
    {
        for ($i = 0; $i < 25; $i++) {
            TenantEntitlementDecision::query()->create([
                'tenant_id' => $this->tenant->id,
                'decision' => TenantEntitlementDecision::DECISION_DENIED,
                'reason_code' => 'over_quota',
            ]);
        }
        $keys = array_column(app(EntitlementAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('entitlement.denial_spike', $keys);
    }

    public function test_onboarding_failed_detected(): void
    {
        TenantProvisioningRun::query()->create([
            'tenant_id' => $this->tenant->id,
            'requested_plan_code' => 'starter',
            'idempotency_key' => 'idem-'.uniqid(),
            'status' => TenantProvisioningRun::STATUS_FAILED,
        ]);
        $keys = array_column(app(OnboardingAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('onboarding.failed_runs', $keys);
    }

    public function test_export_report_denial_spike_detected(): void
    {
        for ($i = 0; $i < 12; $i++) {
            TenantEntitlementDecision::query()->create([
                'tenant_id' => $this->tenant->id,
                'decision' => TenantEntitlementDecision::DECISION_DENIED,
                'reason_code' => 'usage_limit_exceeded',
                'resource_type' => 'export',
                'action' => 'export',
            ]);
        }
        $keys = array_column(app(ExportReportAnomalyService::class)->detect(), 'anomaly_key');
        $this->assertContains('export_report.denial_spike', $keys);
    }

    public function test_dry_run_does_not_persist(): void
    {
        $this->syncBatch(TenantAndroidSyncBatch::STATUS_FAILED, 1);
        $result = app(ObservabilityAnomalyScanService::class)->scan(false);
        $this->assertGreaterThan(0, $result['detected']);
        $this->assertSame(0, $result['persisted']);
        $this->assertSame(0, ObservabilityAnomalyEvent::query()->count());
    }

    public function test_execute_persists_only_observability_events(): void
    {
        $invoice = TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);

        $result = app(ObservabilityAnomalyScanService::class)->scan(true);
        $this->assertGreaterThan(0, $result['persisted']);
        $this->assertGreaterThan(0, ObservabilityAnomalyEvent::query()->count());

        // Domain state untouched: invoice not paid.
        $this->assertSame(TenantBillingInvoice::COLLECTION_OVERDUE, $invoice->fresh()->collection_state);
    }

    public function test_duplicate_anomaly_updates_occurrence_count(): void
    {
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);

        $scan = app(ObservabilityAnomalyScanService::class);
        $scan->scan(true);
        $countAfterFirst = ObservabilityAnomalyEvent::query()->count();
        $scan->scan(true);

        $this->assertSame($countAfterFirst, ObservabilityAnomalyEvent::query()->count(), 'No new rows on re-scan');
        $anomaly = ObservabilityAnomalyEvent::query()->where('anomaly_key', 'billing.overdue_past_grace')->first();
        $this->assertSame(2, $anomaly->occurrence_count);
    }

    public function test_suggestion_created_from_anomaly_without_tenant_mutation(): void
    {
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);
        app(ObservabilityAnomalyScanService::class)->scan(true);

        $result = app(ObservabilityIncidentSuggestionService::class)->generateFromAnomalies($this->admin);
        $this->assertGreaterThan(0, $result['created']);
        // No support incident auto-created.
        $this->assertDatabaseCount('tenant_support_incidents', 0);
        // Tenant not reactivated / suspended by suggestion.
        $this->assertSame(Tenant::STATUS_ACTIVE, $this->tenant->fresh()->status);
    }

    public function test_accept_suggestion_creates_support_incident_via_sprint35(): void
    {
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);
        app(ObservabilityAnomalyScanService::class)->scan(true);
        $svc = app(ObservabilityIncidentSuggestionService::class);
        $svc->generateFromAnomalies($this->admin);

        $suggestion = ObservabilityAlertSuggestion::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($suggestion);

        $accepted = $svc->accept($suggestion, $this->admin, 'incident_linked');
        $this->assertSame(ObservabilityAlertSuggestion::STATUS_LINKED_TO_INCIDENT, $accepted->status);
        $this->assertNotNull($accepted->support_incident_id);
        $this->assertDatabaseCount('tenant_support_incidents', 1);
        // Audited.
        $this->assertDatabaseHas('admin_audit_logs', ['tenant_id' => $this->tenant->id]);
    }

    public function test_dismiss_suggestion_is_audited(): void
    {
        TenantBillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_state' => TenantBillingInvoice::COLLECTION_OVERDUE,
            'due_at' => now()->subDays(30),
        ]);
        app(ObservabilityAnomalyScanService::class)->scan(true);
        $svc = app(ObservabilityIncidentSuggestionService::class);
        $svc->generateFromAnomalies($this->admin);
        $suggestion = ObservabilityAlertSuggestion::query()->first();

        $dismissed = $svc->dismiss($suggestion, $this->admin, 'false_positive');
        $this->assertSame(ObservabilityAlertSuggestion::STATUS_DISMISSED, $dismissed->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'OBSERVABILITY_ALERT_SUGGESTION_DISMISS']);
    }

    private function syncBatch(string $status, int $n): TenantAndroidSyncBatch
    {
        return TenantAndroidSyncBatch::query()->create([
            'tenant_id' => $this->tenant->id,
            'client_batch_id' => 'cb-'.$status.'-'.$n,
            'idempotency_key' => 'ik-'.$status.'-'.$n,
            'status' => $status,
        ]);
    }
}
