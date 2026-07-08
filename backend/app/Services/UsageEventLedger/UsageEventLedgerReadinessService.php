<?php

namespace App\Services\UsageEventLedger;

use App\Models\TenantUsageEvent;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 27 — evaluates whether the usage event ledger foundation is present and
 * safe: the append-only table/model/services exist, metadata redaction exists,
 * the report export meter is live, the UEL rules are locked, and every guardrail
 * flag is disabled. Produces a GO/WATCH/NO_GO decision (never prints secrets).
 */
class UsageEventLedgerReadinessService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    private const EXPECTED_RULES = [
        'UEL-R001', 'UEL-R002', 'UEL-R003', 'UEL-R004', 'UEL-R005', 'UEL-R006',
        'UEL-R007', 'UEL-R008', 'UEL-R009', 'UEL-R010', 'UEL-R011', 'UEL-R012',
        'UEL-R013', 'UEL-R014', 'UEL-R015',
    ];

    private const GUARDRAIL_FLAGS = [
        'usage_ledger_mutable_in_runtime_allowed',
        'client_side_report_export_authoritative',
        'usage_event_metadata_may_store_secrets_allowed',
        'failed_export_counts_usage_allowed',
        'cross_tenant_usage_events_in_runtime_allowed',
    ];

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->ledgerTableSignal(),
            $this->servicesSignal(),
            $this->redactionSignal(),
            $this->meterableSignal(),
            $this->rulesSignal(),
            $this->guardrailSignal(),
            $this->docsSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    private function ledgerTableSignal(): array
    {
        return Schema::hasTable('tenant_usage_events') && class_exists(TenantUsageEvent::class)
            ? $this->signal('ledger_table', self::STATUS_PASS, 'tenant_usage_events table and model present (UEL-R001).')
            : $this->signal('ledger_table', self::STATUS_FAIL, 'tenant_usage_events table/model missing.');
    }

    private function servicesSignal(): array
    {
        $required = [
            UsageEventLedgerService::class,
            UsageEventRecorder::class,
            UsageEventPeriodResolver::class,
            ReportExportMeteringService::class,
        ];
        $missing = array_values(array_filter($required, fn ($c) => ! class_exists($c)));

        return $missing === []
            ? $this->signal('ledger_services', self::STATUS_PASS, count($required).' usage event ledger services present.')
            : $this->signal('ledger_services', self::STATUS_FAIL, 'Missing services: '.implode(', ', $missing));
    }

    private function redactionSignal(): array
    {
        return trait_exists(SanitizesUsageEventMetadata::class)
            ? $this->signal('metadata_redaction', self::STATUS_PASS, 'Usage event metadata redaction present (UEL-R003).')
            : $this->signal('metadata_redaction', self::STATUS_FAIL, 'Usage event metadata redaction missing.');
    }

    private function meterableSignal(): array
    {
        $meterKey = (string) config('usage_event_ledger.report_export_meter_key', 'reports.exports.monthly');
        // The meter key contains dots, so it is a LITERAL array key — never a
        // config dot-path. Read the array and index by the literal key.
        $limits = (array) config('tenant_plan.usage_limits', []);
        $meterable = (bool) ($limits[$meterKey]['meterable'] ?? false);

        return $meterable
            ? $this->signal('report_export_meter', self::STATUS_PASS, $meterKey.' is meterable (UEL-R006).')
            : $this->signal('report_export_meter', self::STATUS_FAIL, $meterKey.' is still deferred (meterable=false).');
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('usage_event_ledger.rules', []);
        $missing = array_values(array_diff(self::EXPECTED_RULES, array_keys($rules)));

        return $missing === []
            ? $this->signal('uel_rules', self::STATUS_PASS, count($rules).' UEL rules locked (UEL-R014/R015).')
            : $this->signal('uel_rules', self::STATUS_FAIL, 'Missing UEL rules: '.implode(', ', $missing));
    }

    private function guardrailSignal(): array
    {
        $enabled = [];
        foreach (self::GUARDRAIL_FLAGS as $flag) {
            if (config('usage_event_ledger.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return $enabled === []
            ? $this->signal('guardrails', self::STATUS_PASS, count(self::GUARDRAIL_FLAGS).' usage ledger guardrails disabled.')
            : $this->signal('guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    private function docsSignal(): array
    {
        $required = (array) config('usage_event_ledger.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('required_docs', self::STATUS_PASS, count($required).' Sprint 27 docs present.')
            : $this->signal('required_docs', self::STATUS_FAIL, 'Missing docs: '.implode(', ', $missing));
    }

    /**
     * @param array<int, array{status:string}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
