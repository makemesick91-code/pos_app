<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sprint 34 — android-runtime:* commands run cleanly and the simulate commands
 * assert their invariants (ADR-R004/R013/R014/R026/R030).
 */
class AndroidRuntimeCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_summary_runs(): void
    {
        $this->artisan('android-runtime:device-summary --json')->assertSuccessful();
    }

    public function test_sync_summary_runs(): void
    {
        $this->artisan('android-runtime:sync-summary --json')->assertSuccessful();
    }

    public function test_cashier_check_posture_runs(): void
    {
        $this->artisan('android-runtime:cashier-check --json')->assertSuccessful();
    }

    public function test_governance_audit_passes(): void
    {
        $this->artisan('android-runtime:governance-audit')->assertSuccessful();
    }

    public function test_go_no_go_passes(): void
    {
        $this->artisan('android-runtime:go-no-go')->assertSuccessful();
    }

    public function test_activation_simulate_execute_is_idempotent(): void
    {
        $this->artisan('android-runtime:activation-simulate --execute --json')->assertSuccessful();
    }

    #[DataProvider('scenarioProvider')]
    public function test_sync_simulate_scenarios(string $scenario): void
    {
        $this->artisan("android-runtime:sync-simulate --scenario={$scenario} --execute")->assertSuccessful();
    }

    public static function scenarioProvider(): array
    {
        return [
            ['valid'],
            ['replay'],
            ['duplicate-item'],
            ['conflict'],
            ['revoked-device'],
            ['suspended-tenant'],
            ['unpaid-past-grace'],
            ['trial-expired'],
        ];
    }
}
