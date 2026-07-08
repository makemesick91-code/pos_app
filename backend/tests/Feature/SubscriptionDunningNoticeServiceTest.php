<?php

namespace Tests\Feature;

use App\Models\SubscriptionDunningNotice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalPolicy;
use App\Models\TenantSubscription;
use App\Services\SubscriptionRenewal\SubscriptionDunningNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SubscriptionDunningNoticeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionDunningNoticeService
    {
        return app(SubscriptionDunningNoticeService::class);
    }

    private function candidate(int $maxNotices = 3): SubscriptionRenewalCandidate
    {
        $policy = SubscriptionRenewalPolicy::query()->create([
            'policy_reference' => 'POL-'.uniqid(),
            'code' => 'C_'.strtoupper(uniqid()),
            'name' => 'Policy',
            'max_manual_dunning_notices' => $maxNotices,
            'requires_manual_approval' => true,
        ]);
        $sub = TenantSubscription::factory()->create(['subscription_plan_id' => SubscriptionPlan::factory()->create()->id, 'ends_at' => now()->addDays(3)]);

        return SubscriptionRenewalCandidate::query()->create([
            'candidate_reference' => 'CAND-'.uniqid(),
            'tenant_id' => $sub->tenant_id,
            'tenant_subscription_id' => $sub->id,
            'policy_id' => $policy->id,
            'status' => SubscriptionRenewalCandidate::STATUS_NEW,
            'renewal_stage' => SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW,
            'priority' => SubscriptionRenewalCandidate::PRIORITY_NORMAL,
        ]);
    }

    public function test_prepare_and_transition_manual_notice(): void
    {
        $notice = $this->service()->prepare($this->candidate(), [
            'notice_type' => SubscriptionDunningNotice::TYPE_RENEWAL_REMINDER,
            'channel' => SubscriptionDunningNotice::CHANNEL_WHATSAPP_MANUAL,
            'summary' => 'Reminder',
        ]);

        $this->assertSame(SubscriptionDunningNotice::STATUS_PLANNED, $notice->status);

        $notice = $this->service()->markPrepared($notice);
        $this->assertSame(SubscriptionDunningNotice::STATUS_PREPARED, $notice->status);

        $notice = $this->service()->markSentManually($notice);
        $this->assertSame(SubscriptionDunningNotice::STATUS_MARKED_SENT_MANUALLY, $notice->status);
        $this->assertNotNull($notice->marked_sent_manually_at);

        $notice = $this->service()->complete($notice);
        $this->assertSame(SubscriptionDunningNotice::STATUS_COMPLETED, $notice->status);
    }

    public function test_respects_max_manual_dunning_notices(): void
    {
        $candidate = $this->candidate(2);
        $this->service()->prepare($candidate, ['notice_type' => 'RENEWAL_REMINDER', 'summary' => 'a']);
        $this->service()->prepare($candidate, ['notice_type' => 'PAYMENT_REMINDER', 'summary' => 'b']);

        $this->expectException(RuntimeException::class);
        $this->service()->prepare($candidate, ['notice_type' => 'OVERDUE_NOTICE', 'summary' => 'c']);
    }

    public function test_cancelled_notice_frees_a_slot(): void
    {
        $candidate = $this->candidate(1);
        $first = $this->service()->prepare($candidate, ['notice_type' => 'RENEWAL_REMINDER', 'summary' => 'a']);
        $this->service()->cancel($first);

        // Should not throw — cancelled notice does not count.
        $second = $this->service()->prepare($candidate, ['notice_type' => 'RENEWAL_REMINDER', 'summary' => 'b']);
        $this->assertSame(SubscriptionDunningNotice::STATUS_PLANNED, $second->status);
    }

    public function test_message_preview_is_redacted(): void
    {
        $notice = $this->service()->prepare($this->candidate(), [
            'notice_type' => 'RENEWAL_REMINDER',
            'summary' => 'Reminder',
            'manual_message_preview' => 'token: abc-secret-999 please pay',
        ]);

        $this->assertStringContainsString('[REDACTED]', (string) $notice->manual_message_preview);
        $this->assertStringNotContainsString('abc-secret-999', (string) $notice->manual_message_preview);
    }

    public function test_summary_asserts_manual_only(): void
    {
        $summary = $this->service()->summary();
        $this->assertTrue($summary['manual_only']);
        $this->assertTrue($summary['no_real_sending']);
    }
}
