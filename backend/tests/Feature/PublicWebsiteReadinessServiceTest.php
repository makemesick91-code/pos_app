<?php

namespace Tests\Feature;

use App\Models\PublicWebsitePage;
use App\Services\PublicWebsite\LandingPageContentService;
use App\Services\PublicWebsite\PublicWebsiteGoNoGoService;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function readiness(): PublicWebsiteReadinessService
    {
        return app(PublicWebsiteReadinessService::class);
    }

    /** Seed a full GO public website state. */
    private function seedGoState(): void
    {
        $svc = $this->readiness();

        foreach (PublicWebsitePage::KEYS as $key) {
            $page = $svc->createPage([
                'page_key' => $key,
                'title' => ucfirst(strtolower($key)),
                'seo_title' => $key.' — Aish POS Lite',
                'seo_description' => 'Deskripsi SEO untuk halaman '.$key.'.',
            ]);
            $svc->publishPage($page);
        }

        $landing = app(LandingPageContentService::class)->create(['headline' => 'Aish POS Lite']);
        app(LandingPageContentService::class)->publish($landing);

        foreach (['OWNER', 'TECHNICAL', 'SALES', 'OPERATIONS', 'LEGAL_PRIVACY'] as $role) {
            $svc->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }
    }

    public function test_empty_state_is_no_go(): void
    {
        $this->assertSame(PublicWebsiteReadinessService::DECISION_NO_GO, $this->readiness()->evaluate()['decision']);
    }

    public function test_full_state_is_go(): void
    {
        $this->seedGoState();

        $report = $this->readiness()->evaluate();
        $this->assertSame(PublicWebsiteReadinessService::DECISION_GO, $report['decision'], json_encode($report['signals']));
    }

    public function test_go_no_go_aggregates_prior_gates_and_readiness(): void
    {
        $this->seedGoState();

        $report = app(PublicWebsiteGoNoGoService::class)->evaluate();
        $this->assertSame(PublicWebsiteGoNoGoService::DECISION_GO, $report['decision'], json_encode($report['signals']));
        foreach ($report['gates'] as $gate => $ok) {
            $this->assertTrue($ok, "Prior gate {$gate} should be satisfied.");
        }
    }

    public function test_rejected_signoff_forces_no_go(): void
    {
        $this->seedGoState();
        $this->readiness()->addSignoff(['signer_role' => 'LEGAL_PRIVACY', 'decision' => 'REJECTED']);

        $this->assertSame(PublicWebsiteReadinessService::DECISION_NO_GO, $this->readiness()->evaluate()['decision']);
    }
}
