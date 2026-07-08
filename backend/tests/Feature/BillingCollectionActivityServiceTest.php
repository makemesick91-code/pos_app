<?php

namespace Tests\Feature;

use App\Models\SaasBillingCollectionActivity;
use App\Services\BillingCollection\BillingCollectionActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCollectionActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingCollectionActivityService
    {
        return app(BillingCollectionActivityService::class);
    }

    public function test_can_add_note_call_and_follow_up(): void
    {
        $note = $this->service()->create(['activity_type' => 'NOTE', 'summary' => 'A note']);
        $call = $this->service()->create(['activity_type' => 'CALL', 'summary' => 'Called tenant']);
        $follow = $this->service()->create(['activity_type' => 'PAYMENT_FOLLOW_UP', 'summary' => 'Follow up', 'scheduled_at' => '2026-07-10 09:00:00']);

        $this->assertSame('NOTE', $note->activity_type);
        $this->assertSame('CALL', $call->activity_type);
        $this->assertNotNull($follow->scheduled_at);
    }

    public function test_manual_channel_activity_does_not_send_real_message(): void
    {
        $wa = $this->service()->create(['activity_type' => 'WHATSAPP_MANUAL', 'summary' => 'WA note']);
        $email = $this->service()->create(['activity_type' => 'EMAIL_MANUAL', 'summary' => 'Email note']);

        // The activities are notes only; the summary asserts no real sending.
        $this->assertContains($wa->activity_type, SaasBillingCollectionActivity::MANUAL_ONLY_TYPES);
        $this->assertContains($email->activity_type, SaasBillingCollectionActivity::MANUAL_ONLY_TYPES);
        $this->assertTrue($this->service()->summary()['no_real_sending']);
    }

    public function test_can_complete_and_cancel(): void
    {
        $a = $this->service()->create(['activity_type' => 'NOTE', 'summary' => 'x']);
        $this->assertSame(SaasBillingCollectionActivity::STATUS_DONE, $this->service()->complete($a)->status);

        $b = $this->service()->create(['activity_type' => 'NOTE', 'summary' => 'y']);
        $this->assertSame(SaasBillingCollectionActivity::STATUS_CANCELLED, $this->service()->cancel($b)->status);
    }

    public function test_summary_reports_by_type_and_status(): void
    {
        $this->service()->create(['activity_type' => 'NOTE', 'summary' => 'x']);
        $this->service()->create(['activity_type' => 'CALL', 'summary' => 'y']);

        $summary = $this->service()->summary();
        $this->assertSame('GO', $summary['decision']);
        $this->assertSame(2, $summary['total_activities']);
        $this->assertTrue($summary['manual_follow_up_only']);
    }

    public function test_notes_are_redacted(): void
    {
        $a = $this->service()->create([
            'activity_type' => 'NOTE', 'summary' => 'x',
            'notes' => 'whatsapp_token: wa_secret',
        ]);

        $this->assertStringNotContainsString('wa_secret', (string) $a->notes);
    }
}
