<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A platform admin action audit record (Sprint 11). Records who did what to
 * which target, with sanitized before/after snapshots. Never stores secrets or
 * raw payment gateway payloads. See Sprint 11 evidence.
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const ACTION_TENANT_VIEWED = 'TENANT_VIEWED';
    public const ACTION_SUBSCRIPTION_ASSIGNED = 'SUBSCRIPTION_ASSIGNED';
    public const ACTION_SUBSCRIPTION_UPDATED = 'SUBSCRIPTION_UPDATED';
    public const ACTION_DEVICE_REVOKED = 'DEVICE_REVOKED';
    public const ACTION_PLAN_CREATED = 'PLAN_CREATED';
    public const ACTION_PLAN_UPDATED = 'PLAN_UPDATED';
    public const ACTION_PLAN_DEACTIVATED = 'PLAN_DEACTIVATED';
    public const ACTION_TENANT_ONBOARDED = 'TENANT_ONBOARDED';
    public const ACTION_TENANT_ONBOARDING_REPLAYED = 'TENANT_ONBOARDING_REPLAYED';
    public const ACTION_DEMO_DATA_SEEDED = 'DEMO_DATA_SEEDED';
    public const ACTION_DEMO_DATA_RESET = 'DEMO_DATA_RESET';
    public const ACTION_DEFECT_CREATED = 'DEFECT_CREATED';
    public const ACTION_DEFECT_UPDATED = 'DEFECT_UPDATED';
    public const ACTION_DEFECT_ASSIGNED = 'DEFECT_ASSIGNED';
    public const ACTION_DEFECT_STATUS_CHANGED = 'DEFECT_STATUS_CHANGED';
    public const ACTION_DEFECT_ACCEPTED_RISK = 'DEFECT_ACCEPTED_RISK';
    public const ACTION_DEFECT_FIXED = 'DEFECT_FIXED';
    public const ACTION_DEFECT_VERIFIED = 'DEFECT_VERIFIED';
    public const ACTION_CLOSURE_CREATED = 'CLOSURE_CREATED';
    public const ACTION_CLOSURE_APPROVED = 'CLOSURE_APPROVED';
    public const ACTION_CLOSURE_BLOCKED = 'CLOSURE_BLOCKED';
    public const ACTION_HANDOVER_CREATED = 'HANDOVER_CREATED';
    public const ACTION_HANDOVER_UPDATED = 'HANDOVER_UPDATED';
    public const ACTION_HANDOVER_MARKED_READY = 'HANDOVER_MARKED_READY';
    public const ACTION_HANDOVER_HANDED_OVER = 'HANDOVER_HANDED_OVER';
    public const ACTION_HANDOVER_SIGNOFF_ADDED = 'HANDOVER_SIGNOFF_ADDED';
    public const ACTION_OPERATION_RUN_CREATED = 'OPERATION_RUN_CREATED';
    public const ACTION_OPERATION_RUN_APPROVED = 'OPERATION_RUN_APPROVED';
    public const ACTION_OPERATION_RUN_BLOCKED = 'OPERATION_RUN_BLOCKED';
    public const ACTION_INCIDENT_CREATED = 'INCIDENT_CREATED';
    public const ACTION_INCIDENT_UPDATED = 'INCIDENT_UPDATED';
    public const ACTION_INCIDENT_ASSIGNED = 'INCIDENT_ASSIGNED';
    public const ACTION_INCIDENT_STATUS_CHANGED = 'INCIDENT_STATUS_CHANGED';
    public const ACTION_INCIDENT_ACCEPTED_RISK = 'INCIDENT_ACCEPTED_RISK';
    public const ACTION_MAINTENANCE_CREATED = 'MAINTENANCE_CREATED';
    public const ACTION_MAINTENANCE_UPDATED = 'MAINTENANCE_UPDATED';
    public const ACTION_MAINTENANCE_STATUS_CHANGED = 'MAINTENANCE_STATUS_CHANGED';
    public const ACTION_LAUNCH_RUN_CREATED = 'LAUNCH_RUN_CREATED';
    public const ACTION_LAUNCH_RUN_APPROVED = 'LAUNCH_RUN_APPROVED';
    public const ACTION_LAUNCH_RUN_BLOCKED = 'LAUNCH_RUN_BLOCKED';
    public const ACTION_LAUNCH_SIGNOFF_ADDED = 'LAUNCH_SIGNOFF_ADDED';
    public const ACTION_PACKAGE_CREATED = 'PACKAGE_CREATED';
    public const ACTION_PACKAGE_UPDATED = 'PACKAGE_UPDATED';
    public const ACTION_PACKAGE_APPROVED = 'PACKAGE_APPROVED';
    public const ACTION_PACKAGE_RETIRED = 'PACKAGE_RETIRED';
    public const ACTION_COMMERCIAL_RISK_CREATED = 'COMMERCIAL_RISK_CREATED';
    public const ACTION_COMMERCIAL_RISK_UPDATED = 'COMMERCIAL_RISK_UPDATED';
    public const ACTION_COMMERCIAL_RISK_ACCEPTED = 'COMMERCIAL_RISK_ACCEPTED';
    public const ACTION_COMMERCIAL_RISK_CLOSED = 'COMMERCIAL_RISK_CLOSED';

    // Sprint 21 — public website / landing page readiness.
    public const ACTION_WEBSITE_PAGE_CREATED = 'WEBSITE_PAGE_CREATED';
    public const ACTION_WEBSITE_PAGE_UPDATED = 'WEBSITE_PAGE_UPDATED';
    public const ACTION_WEBSITE_PAGE_APPROVED = 'WEBSITE_PAGE_APPROVED';
    public const ACTION_WEBSITE_PAGE_PUBLISHED = 'WEBSITE_PAGE_PUBLISHED';
    public const ACTION_WEBSITE_PAGE_ARCHIVED = 'WEBSITE_PAGE_ARCHIVED';
    public const ACTION_LANDING_VERSION_CREATED = 'LANDING_VERSION_CREATED';
    public const ACTION_LANDING_VERSION_UPDATED = 'LANDING_VERSION_UPDATED';
    public const ACTION_LANDING_VERSION_APPROVED = 'LANDING_VERSION_APPROVED';
    public const ACTION_LANDING_VERSION_PUBLISHED = 'LANDING_VERSION_PUBLISHED';
    public const ACTION_LANDING_VERSION_ARCHIVED = 'LANDING_VERSION_ARCHIVED';
    public const ACTION_LEAD_STATUS_CHANGED = 'LEAD_STATUS_CHANGED';
    public const ACTION_WEBSITE_RISK_CREATED = 'WEBSITE_RISK_CREATED';
    public const ACTION_WEBSITE_RISK_UPDATED = 'WEBSITE_RISK_UPDATED';
    public const ACTION_WEBSITE_RISK_ACCEPTED = 'WEBSITE_RISK_ACCEPTED';
    public const ACTION_WEBSITE_RISK_CLOSED = 'WEBSITE_RISK_CLOSED';
    public const ACTION_WEBSITE_SIGNOFF_ADDED = 'WEBSITE_SIGNOFF_ADDED';

    // Sprint 22 — lead management / sales pipeline readiness.
    public const ACTION_SALES_STAGE_CREATED = 'SALES_STAGE_CREATED';
    public const ACTION_SALES_STAGE_UPDATED = 'SALES_STAGE_UPDATED';
    public const ACTION_SALES_STAGES_ENSURED = 'SALES_STAGES_ENSURED';
    public const ACTION_SALES_LEAD_CREATED = 'SALES_LEAD_CREATED';
    public const ACTION_SALES_LEAD_UPDATED = 'SALES_LEAD_UPDATED';
    public const ACTION_SALES_LEAD_IMPORTED = 'SALES_LEAD_IMPORTED';
    public const ACTION_SALES_LEAD_TRANSITIONED = 'SALES_LEAD_TRANSITIONED';
    public const ACTION_SALES_LEAD_QUALIFIED = 'SALES_LEAD_QUALIFIED';
    public const ACTION_SALES_LEAD_LOST = 'SALES_LEAD_LOST';
    public const ACTION_SALES_LEAD_READY_FOR_ONBOARDING = 'SALES_LEAD_READY_FOR_ONBOARDING';
    public const ACTION_SALES_ACTIVITY_CREATED = 'SALES_ACTIVITY_CREATED';
    public const ACTION_SALES_ACTIVITY_COMPLETED = 'SALES_ACTIVITY_COMPLETED';
    public const ACTION_SALES_ACTIVITY_CANCELLED = 'SALES_ACTIVITY_CANCELLED';
    public const ACTION_SALES_LEAD_ASSIGNED = 'SALES_LEAD_ASSIGNED';
    public const ACTION_SALES_LEAD_UNASSIGNED = 'SALES_LEAD_UNASSIGNED';
    public const ACTION_SALES_RISK_CREATED = 'SALES_RISK_CREATED';
    public const ACTION_SALES_RISK_UPDATED = 'SALES_RISK_UPDATED';
    public const ACTION_SALES_RISK_ACCEPTED = 'SALES_RISK_ACCEPTED';
    public const ACTION_SALES_RISK_CLOSED = 'SALES_RISK_CLOSED';
    public const ACTION_SALES_SIGNOFF_ADDED = 'SALES_SIGNOFF_ADDED';

    // Sprint 23 — billing collection governance.
    public const ACTION_BILLING_ACCOUNT_CREATED = 'BILLING_ACCOUNT_CREATED';
    public const ACTION_BILLING_ACCOUNT_UPDATED = 'BILLING_ACCOUNT_UPDATED';
    public const ACTION_BILLING_CYCLE_CREATED = 'BILLING_CYCLE_CREATED';
    public const ACTION_BILLING_CYCLE_UPDATED = 'BILLING_CYCLE_UPDATED';
    public const ACTION_BILLING_CYCLE_TRANSITIONED = 'BILLING_CYCLE_TRANSITIONED';
    public const ACTION_BILLING_INVOICE_CREATED = 'BILLING_INVOICE_CREATED';
    public const ACTION_BILLING_INVOICE_UPDATED = 'BILLING_INVOICE_UPDATED';
    public const ACTION_BILLING_INVOICE_LINE_ADDED = 'BILLING_INVOICE_LINE_ADDED';
    public const ACTION_BILLING_INVOICE_LINE_UPDATED = 'BILLING_INVOICE_LINE_UPDATED';
    public const ACTION_BILLING_INVOICE_ISSUED = 'BILLING_INVOICE_ISSUED';
    public const ACTION_BILLING_INVOICE_OVERDUE = 'BILLING_INVOICE_OVERDUE';
    public const ACTION_BILLING_INVOICE_DISPUTED = 'BILLING_INVOICE_DISPUTED';
    public const ACTION_BILLING_INVOICE_VOIDED = 'BILLING_INVOICE_VOIDED';
    public const ACTION_BILLING_PAYMENT_EVIDENCE_SUBMITTED = 'BILLING_PAYMENT_EVIDENCE_SUBMITTED';
    public const ACTION_BILLING_PAYMENT_EVIDENCE_UNDER_REVIEW = 'BILLING_PAYMENT_EVIDENCE_UNDER_REVIEW';
    public const ACTION_BILLING_PAYMENT_EVIDENCE_ACCEPTED = 'BILLING_PAYMENT_EVIDENCE_ACCEPTED';
    public const ACTION_BILLING_PAYMENT_EVIDENCE_REJECTED = 'BILLING_PAYMENT_EVIDENCE_REJECTED';
    public const ACTION_BILLING_PAYMENT_EVIDENCE_VOIDED = 'BILLING_PAYMENT_EVIDENCE_VOIDED';
    public const ACTION_BILLING_ACTIVITY_CREATED = 'BILLING_ACTIVITY_CREATED';
    public const ACTION_BILLING_ACTIVITY_COMPLETED = 'BILLING_ACTIVITY_COMPLETED';
    public const ACTION_BILLING_ACTIVITY_CANCELLED = 'BILLING_ACTIVITY_CANCELLED';
    public const ACTION_BILLING_RISK_CREATED = 'BILLING_RISK_CREATED';
    public const ACTION_BILLING_RISK_UPDATED = 'BILLING_RISK_UPDATED';
    public const ACTION_BILLING_RISK_ACCEPTED = 'BILLING_RISK_ACCEPTED';
    public const ACTION_BILLING_RISK_CLOSED = 'BILLING_RISK_CLOSED';
    public const ACTION_BILLING_SIGNOFF_ADDED = 'BILLING_SIGNOFF_ADDED';

    public const TARGET_TENANT = 'tenant';
    public const TARGET_SUBSCRIPTION = 'tenant_subscription';
    public const TARGET_DEVICE = 'registered_device';
    public const TARGET_PLAN = 'subscription_plan';
    public const TARGET_ONBOARDING_RUN = 'tenant_onboarding_run';
    public const TARGET_PILOT_DEFECT = 'pilot_defect';
    public const TARGET_PILOT_CLOSURE_RUN = 'pilot_closure_run';
    public const TARGET_PRODUCTION_HANDOVER_PACKAGE = 'production_handover_package';
    public const TARGET_PRODUCTION_HANDOVER_SIGNOFF = 'production_handover_signoff';
    public const TARGET_PRODUCTION_OPERATION_RUN = 'production_operation_run';
    public const TARGET_PRODUCTION_INCIDENT = 'production_incident';
    public const TARGET_PRODUCTION_MAINTENANCE_WINDOW = 'production_maintenance_window';
    public const TARGET_COMMERCIAL_LAUNCH_RUN = 'commercial_launch_run';
    public const TARGET_COMMERCIAL_LAUNCH_SIGNOFF = 'commercial_launch_signoff';
    public const TARGET_SAAS_PACKAGE_CATALOG = 'saas_package_catalog';
    public const TARGET_COMMERCIAL_LAUNCH_RISK = 'commercial_launch_risk';
    public const TARGET_PUBLIC_WEBSITE_PAGE = 'public_website_page';
    public const TARGET_LANDING_PAGE_VERSION = 'landing_page_version';
    public const TARGET_LEAD_INTEREST_SUBMISSION = 'lead_interest_submission';
    public const TARGET_PUBLIC_WEBSITE_RISK = 'public_website_risk';
    public const TARGET_PUBLIC_WEBSITE_SIGNOFF = 'public_website_signoff';
    public const TARGET_SALES_PIPELINE_STAGE = 'sales_pipeline_stage';
    public const TARGET_SALES_LEAD = 'sales_lead';
    public const TARGET_SALES_LEAD_ACTIVITY = 'sales_lead_activity';
    public const TARGET_SALES_LEAD_ASSIGNMENT = 'sales_lead_assignment';
    public const TARGET_SALES_PIPELINE_RISK = 'sales_pipeline_risk';
    public const TARGET_SALES_PIPELINE_SIGNOFF = 'sales_pipeline_signoff';

    // Sprint 23 — billing collection governance.
    public const TARGET_SAAS_BILLING_ACCOUNT = 'saas_billing_account';
    public const TARGET_SAAS_BILLING_CYCLE = 'saas_billing_cycle';
    public const TARGET_SAAS_BILLING_INVOICE = 'saas_billing_invoice';
    public const TARGET_SAAS_BILLING_INVOICE_LINE = 'saas_billing_invoice_line';
    public const TARGET_SAAS_BILLING_PAYMENT_EVIDENCE = 'saas_billing_payment_evidence';
    public const TARGET_SAAS_BILLING_COLLECTION_ACTIVITY = 'saas_billing_collection_activity';
    public const TARGET_SAAS_BILLING_COLLECTION_RISK = 'saas_billing_collection_risk';
    public const TARGET_SAAS_BILLING_COLLECTION_SIGNOFF = 'saas_billing_collection_signoff';

    protected $fillable = [
        'actor_user_id',
        'action',
        'target_type',
        'target_id',
        'tenant_id',
        'before_values',
        'after_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForActor(Builder $query, int $actorUserId): Builder
    {
        return $query->where('actor_user_id', $actorUserId);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
