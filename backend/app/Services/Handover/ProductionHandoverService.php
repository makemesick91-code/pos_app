<?php

namespace App\Services\Handover;

use App\Models\PilotClosureRun;
use App\Models\ProductionHandoverPackage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Sprint 18 — production handover package orchestration.
 *
 * Assembles a production handover package from the release-readiness, operator/
 * admin handover, support/SLA handover, backup/restore handover, and release
 * ownership-matrix documentation contract (doc presence + structural checks),
 * persists it, and transitions its status conservatively (READY only when the
 * package evaluation is GO; WATCH on warnings; BLOCKED on any missing required
 * doc). candidate_commit/tag are references only — never credentials.
 */
class ProductionHandoverService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * Evaluate the handover documentation/readiness contract (no DB rows needed).
     *
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->docsSignal(),
            $this->docSignal('operator_admin_handover', 'docs/handover/operator-admin-handover.md'),
            $this->docSignal('support_sla_handover', 'docs/handover/support-sla-handover.md'),
            $this->docSignal('backup_restore_handover', 'docs/handover/backup-restore-handover.md'),
            $this->ownershipMatrixSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): ProductionHandoverPackage
    {
        $evaluation = $this->evaluate();

        $closureId = $attributes['pilot_closure_run_id'] ?? null;
        if ($closureId !== null) {
            // Only accept an existing closure reference.
            $closureId = PilotClosureRun::query()->whereKey($closureId)->value('id');
        }

        return ProductionHandoverPackage::query()->create([
            'handover_reference' => (string) ($attributes['handover_reference'] ?? $this->generateReference()),
            'pilot_closure_run_id' => $closureId,
            'status' => ProductionHandoverPackage::STATUS_DRAFT,
            'decision' => $evaluation['decision'],
            'candidate_commit' => $attributes['candidate_commit'] ?? null,
            'candidate_tag' => $attributes['candidate_tag'] ?? null,
            'production_readiness_summary' => $attributes['production_readiness_summary'] ?? null,
            'operator_handover_summary' => $attributes['operator_handover_summary'] ?? null,
            'admin_handover_summary' => $attributes['admin_handover_summary'] ?? null,
            'support_sla_summary' => $attributes['support_sla_summary'] ?? null,
            'backup_restore_summary' => $attributes['backup_restore_summary'] ?? null,
            'ownership_matrix' => $attributes['ownership_matrix'] ?? null,
            'checklist' => $this->checklistFrom($evaluation['signals']),
            'evidence_references' => $attributes['evidence_references'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(ProductionHandoverPackage $package, array $attributes): ProductionHandoverPackage
    {
        $allowed = [
            'candidate_commit', 'candidate_tag', 'production_readiness_summary',
            'operator_handover_summary', 'admin_handover_summary', 'support_sla_summary',
            'backup_restore_summary', 'ownership_matrix', 'evidence_references', 'metadata',
        ];

        $dirty = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $attributes)) {
                $dirty[$key] = $attributes[$key];
            }
        }

        if ($dirty !== []) {
            $package->fill($dirty)->save();
        }

        return $package->refresh();
    }

    /**
     * Conservative READY transition: READY only when the documentation contract is
     * GO; WATCH on warnings; BLOCKED when any required doc is missing.
     */
    public function markReady(ProductionHandoverPackage $package): ProductionHandoverPackage
    {
        $evaluation = $this->evaluate();
        $package->decision = $evaluation['decision'];
        $package->status = match ($evaluation['decision']) {
            self::DECISION_GO => ProductionHandoverPackage::STATUS_READY,
            self::DECISION_WATCH => ProductionHandoverPackage::STATUS_WATCH,
            default => ProductionHandoverPackage::STATUS_BLOCKED,
        };
        $package->checklist = $this->checklistFrom($evaluation['signals']);
        $package->save();

        return $package->refresh();
    }

    public function markHandedOver(ProductionHandoverPackage $package, ?User $actor = null): ProductionHandoverPackage
    {
        // Conservative: only a READY package may be handed over.
        if ($package->status !== ProductionHandoverPackage::STATUS_READY) {
            return $package;
        }

        $package->status = ProductionHandoverPackage::STATUS_HANDED_OVER;
        $package->approved_by = $actor?->id;
        $package->approved_at = now();
        $package->save();

        return $package->refresh();
    }

    /**
     * @param array<int,array{status:string}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /**
     * @param array<int,array{key:string,status:string}> $signals
     * @return array<string,string>
     */
    private function checklistFrom(array $signals): array
    {
        $out = [];
        foreach ($signals as $signal) {
            $out[$signal['key']] = $signal['status'];
        }

        return $out;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function docsSignal(): array
    {
        $docs = (array) config('production_handover.required_docs', []);
        $missing = [];
        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('handover_docs', self::STATUS_PASS, count($docs).' handover docs present.')
            : $this->signal('handover_docs', self::STATUS_FAIL, 'Missing handover docs: '.implode(', ', $missing));
    }

    private function docSignal(string $key, string $path): array
    {
        return is_file($this->repoRoot().'/'.ltrim($path, '/'))
            ? $this->signal($key, self::STATUS_PASS, basename($path).' present.')
            : $this->signal($key, self::STATUS_FAIL, "Missing {$path}.");
    }

    private function ownershipMatrixSignal(): array
    {
        $path = $this->repoRoot().'/docs/handover/release-ownership-matrix.md';
        if (! is_file($path)) {
            return $this->signal('ownership_matrix', self::STATUS_FAIL, 'Missing release-ownership-matrix.md.');
        }

        $content = (string) file_get_contents($path);
        $hasTable = str_contains($content, '| Area |') || str_contains($content, '|------|');

        return $hasTable
            ? $this->signal('ownership_matrix', self::STATUS_PASS, 'Ownership matrix table present.')
            : $this->signal('ownership_matrix', self::STATUS_WARN, 'Ownership matrix present but no table detected.');
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    private function generateReference(): string
    {
        return 'HND-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
