<?php

namespace Tests\Feature;

use App\Models\LandingPageVersion;
use App\Models\SaasPackageCatalog;
use App\Services\PublicWebsite\LandingPageContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LandingPageContentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LandingPageContentService
    {
        return app(LandingPageContentService::class);
    }

    public function test_create_approve_publish_supersedes_previous(): void
    {
        $svc = $this->service();
        $v1 = $svc->create(['headline' => 'First', 'hero_cta_target' => '#interest']);
        $svc->publish($v1);

        $v2 = $svc->create(['headline' => 'Second']);
        $svc->approve($v2);
        $svc->publish($v2);

        $this->assertSame(LandingPageVersion::STATUS_ARCHIVED, $v1->refresh()->status);
        $this->assertSame(LandingPageVersion::STATUS_PUBLISHED, $v2->refresh()->status);
        $this->assertSame(1, LandingPageVersion::query()->published()->count());
    }

    public function test_disallowed_cta_target_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->create(['headline' => 'X', 'hero_cta_target' => '/signup']);
    }

    public function test_secret_in_headline_is_redacted(): void
    {
        $version = $this->service()->create([
            'headline' => 'password: supersecret123 promo',
        ]);

        $this->assertStringNotContainsString('supersecret123', $version->headline);
        $this->assertStringContainsString('[REDACTED]', $version->headline);
    }

    public function test_summary_no_go_without_approved_version(): void
    {
        $this->assertSame(LandingPageContentService::DECISION_NO_GO, $this->service()->summary()['decision']);
    }

    public function test_package_highlight_without_active_package_is_watch(): void
    {
        $svc = $this->service();
        $v = $svc->create(['headline' => 'X', 'package_highlights' => ['STARTER']]);
        $svc->approve($v);
        $svc->publish($v);

        $this->assertSame(LandingPageContentService::DECISION_WATCH, $svc->summary()['decision']);
    }

    public function test_summary_go_when_highlights_align(): void
    {
        SaasPackageCatalog::query()->create([
            'package_code' => 'STARTER', 'name' => 'Starter', 'target_segment' => 'GENERAL_UMKM',
            'monthly_price' => 99000, 'device_limit' => 2, 'status' => SaasPackageCatalog::STATUS_ACTIVE,
        ]);

        $svc = $this->service();
        $v = $svc->create(['headline' => 'X', 'package_highlights' => ['STARTER']]);
        $svc->approve($v);
        $svc->publish($v);

        $this->assertSame(LandingPageContentService::DECISION_GO, $svc->summary()['decision']);
    }
}
