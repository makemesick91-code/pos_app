<?php

namespace App\Services\Commercial;

/**
 * Sprint 20 — sales enablement readiness.
 *
 * Verifies the sales enablement documentation contract (offer sheet / commercial
 * FAQ / proposal handoff in the sales enablement pack, pricing governance, and the
 * onboarding capacity reference) exists and carries the required sections, then
 * derives a GO/WATCH/NO_GO decision. Reads files only — never sends anything to a
 * real customer and never opens public signup.
 *
 * NO_GO — a required sales enablement doc is missing.
 * WATCH — all docs exist but a recommended section heading is absent.
 * GO    — all docs exist with the required section headings.
 */
class SalesEnablementReadinessService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** Section heading fragments the sales enablement pack should document. */
    private const RECOMMENDED_SECTIONS = [
        'offer sheet',
        'commercial faq',
        'proposal',
    ];

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $docs = (array) config('commercial_launch.sales_enablement_docs', []);
        $missing = [];
        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        $missingSections = [];
        $pack = $this->repoRoot().'/docs/commercial/sales-enablement-pack.md';
        if (is_file($pack)) {
            $content = strtolower((string) file_get_contents($pack));
            foreach (self::RECOMMENDED_SECTIONS as $section) {
                if (! str_contains($content, $section)) {
                    $missingSections[] = $section;
                }
            }
        }

        $decision = self::DECISION_GO;
        if ($missing !== []) {
            $decision = self::DECISION_NO_GO;
        } elseif ($missingSections !== []) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'required_docs' => $docs,
            'missing_docs' => $missing,
            'missing_sections' => $missingSections,
        ];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
