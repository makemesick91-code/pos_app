<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalPolicy;
use App\Services\SubscriptionRenewal\SubscriptionRenewalPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SubscriptionRenewalPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalPolicyService
    {
        return app(SubscriptionRenewalPolicyService::class);
    }

    public function test_can_create_and_update_policy(): void
    {
        $policy = $this->service()->create([
            'code' => 'monthly_manual',
            'name' => 'Monthly Manual',
            'renewal_window_days' => 10,
            'grace_period_days' => 5,
            'dunning_start_days_before_expiry' => 4,
        ]);

        $this->assertSame('MONTHLY_MANUAL', $policy->code);

        $updated = $this->service()->update($policy, ['renewal_window_days' => 20]);
        $this->assertSame(20, $updated->renewal_window_days);
    }

    public function test_invalid_windows_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->create([
            'code' => 'bad',
            'name' => 'Bad',
            'renewal_window_days' => 5,
            'dunning_start_days_before_expiry' => 10, // exceeds renewal window
        ]);
    }

    public function test_ensure_default_is_idempotent(): void
    {
        $first = $this->service()->ensureDefault();
        $second = $this->service()->ensureDefault();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SubscriptionRenewalPolicy::query()->count());
        $this->assertSame('DEFAULT_MANUAL_RENEWAL', $first->code);
    }

    public function test_policy_summary_does_not_enable_automation(): void
    {
        $summary = $this->service()->summary();

        $this->assertFalse($summary['auto_charge']);
        $this->assertFalse($summary['auto_suspension']);
        $this->assertFalse($summary['real_sending']);
    }

    public function test_secret_like_values_are_redacted(): void
    {
        $policy = $this->service()->create([
            'code' => 'sec',
            'name' => 'Policy',
            'description' => 'api_key: sk_live_supersecret token: abc123',
            'metadata' => ['midtrans_server_key' => 'SB-Mid-server-XYZ'],
        ]);

        $this->assertStringContainsString('[REDACTED]', (string) $policy->description);
        $this->assertStringNotContainsString('sk_live_supersecret', (string) $policy->description);
        $this->assertSame('[REDACTED]', $policy->metadata['midtrans_server_key']);
    }
}
