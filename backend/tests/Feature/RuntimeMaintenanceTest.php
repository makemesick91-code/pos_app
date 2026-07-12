<?php

namespace Tests\Feature;

use App\Services\RuntimeMaintenance\RuntimeMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RuntimeMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_commands_are_registered(): void
    {
        $all = Artisan::all();
        $this->assertArrayHasKey('pilot:runtime-storage-status', $all);
        $this->assertArrayHasKey('pilot:prune-sessions', $all);
        $this->assertArrayHasKey('pilot:prune-cache', $all);
    }

    public function test_status_json_is_valid_and_has_decision(): void
    {
        Artisan::call('pilot:runtime-storage-status', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('decision', $decoded);
        $this->assertContains($decoded['decision'], ['GO', 'WATCH', 'NO-GO']);
        $this->assertArrayHasKey('tables', $decoded);
        $this->assertArrayHasKey('sessions', $decoded['tables']);
        // On the sqlite test driver, PG size probes must be null (not an error).
        $this->assertNull($decoded['tables']['sessions']['size_bytes']);
        $this->assertNull($decoded['database_size_bytes']);
    }

    public function test_status_output_contains_no_secret(): void
    {
        config(['app.key' => 'base64:RUNTIMESTORAGESECRETKEY12345678901234567890=']);
        Artisan::call('pilot:runtime-storage-status', ['--json' => true]);
        $this->assertStringNotContainsString('RUNTIMESTORAGESECRETKEY', Artisan::output());
    }

    public function test_status_flags_failed_jobs_and_stale_queue(): void
    {
        // 120 failed jobs -> NO-GO by failed-job threshold.
        for ($i = 0; $i < 120; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'x',
                'failed_at' => now(),
            ]);
        }

        $service = app(RuntimeMaintenanceService::class);
        $report = $service->status();

        $this->assertSame(120, $report['failed_jobs']['count']);
        $this->assertSame('NO-GO', $report['decision']);

        // Command must exit non-zero on NO-GO.
        $exit = Artisan::call('pilot:runtime-storage-status');
        $this->assertSame(1, $exit);
    }

    public function test_prune_sessions_dry_run_keeps_rows_then_apply_removes_only_expired(): void
    {
        $now = time();
        DB::table('sessions')->insert([
            ['id' => 'old', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => 'e30=', 'last_activity' => $now - (300 * 3600)],
            ['id' => 'fresh', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => 'e30=', 'last_activity' => $now],
        ]);

        // Dry-run: nothing deleted, reports 1 candidate.
        Artisan::call('pilot:prune-sessions', ['--hours' => 168, '--json' => true]);
        $dry = json_decode(Artisan::output(), true);
        $this->assertTrue($dry['dry_run']);
        $this->assertSame(1, $dry['candidates']);
        $this->assertSame(2, DB::table('sessions')->count());

        // Apply: removes only the expired session, keeps the fresh one.
        Artisan::call('pilot:prune-sessions', ['--hours' => 168, '--apply' => true, '--json' => true]);
        $applied = json_decode(Artisan::output(), true);
        $this->assertFalse($applied['dry_run']);
        $this->assertSame(1, $applied['deleted']);
        $this->assertNull(DB::table('sessions')->find('old'));
        $this->assertNotNull(DB::table('sessions')->find('fresh'));
    }

    public function test_prune_cache_removes_only_expired_rows(): void
    {
        $now = time();
        DB::table('cache')->insert([
            ['key' => 'expired', 'value' => 'x', 'expiration' => $now - 60],
            ['key' => 'future', 'value' => 'x', 'expiration' => $now + 3600],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'lock_expired', 'owner' => 'o', 'expiration' => $now - 60],
            ['key' => 'lock_future', 'owner' => 'o', 'expiration' => $now + 3600],
        ]);

        // Dry-run keeps everything.
        Artisan::call('pilot:prune-cache', ['--json' => true]);
        $dry = json_decode(Artisan::output(), true);
        $this->assertSame(1, $dry['cache_candidates']);
        $this->assertSame(1, $dry['lock_candidates']);
        $this->assertSame(2, DB::table('cache')->count());

        // Apply removes only expired rows.
        Artisan::call('pilot:prune-cache', ['--apply' => true, '--json' => true]);
        $applied = json_decode(Artisan::output(), true);
        $this->assertSame(1, $applied['cache_deleted']);
        $this->assertSame(1, $applied['lock_deleted']);
        $this->assertNull(DB::table('cache')->where('key', 'expired')->first());
        $this->assertNotNull(DB::table('cache')->where('key', 'future')->first());
        $this->assertNull(DB::table('cache_locks')->where('key', 'lock_expired')->first());
        $this->assertNotNull(DB::table('cache_locks')->where('key', 'lock_future')->first());
    }

    public function test_prune_sessions_rejects_invalid_hours(): void
    {
        $exit = Artisan::call('pilot:prune-sessions', ['--hours' => 0]);
        $this->assertSame(1, $exit);
    }
}
