<?php

namespace Tests\Feature;

use App\Models\PublicWebsitePage;
use App\Services\PublicWebsite\LandingPageContentService;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_runs_and_reports_no_go_when_empty(): void
    {
        $this->artisan('public-website:readiness')->assertExitCode(1);
    }

    public function test_lead_summary_command_is_go_and_secret_free(): void
    {
        $this->artisan('public-website:lead-summary')
            ->expectsOutputToContain('Interest-only: PASS')
            ->assertExitCode(0);
    }

    public function test_content_summary_command_runs(): void
    {
        $this->artisan('public-website:content-summary')->assertExitCode(1);
    }

    public function test_go_no_go_command_runs_json(): void
    {
        $this->artisan('public-website:go-no-go --json')->assertExitCode(1);
    }

    public function test_readiness_command_go_when_seeded(): void
    {
        $svc = app(PublicWebsiteReadinessService::class);
        foreach (PublicWebsitePage::KEYS as $key) {
            $page = $svc->createPage([
                'page_key' => $key, 'title' => $key,
                'seo_title' => $key, 'seo_description' => 'desc '.$key,
            ]);
            $svc->publishPage($page);
        }
        $landing = app(LandingPageContentService::class)->create(['headline' => 'X']);
        app(LandingPageContentService::class)->publish($landing);
        foreach (['OWNER', 'TECHNICAL', 'SALES', 'OPERATIONS', 'LEGAL_PRIVACY'] as $role) {
            $svc->addSignoff(['signer_role' => $role, 'decision' => 'APPROVED']);
        }

        $this->artisan('public-website:readiness --strict')->assertExitCode(0);
    }
}
