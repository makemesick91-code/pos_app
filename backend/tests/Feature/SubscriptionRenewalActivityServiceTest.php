<?php

namespace Tests\Feature;

use App\Models\SubscriptionRenewalActivity;
use App\Services\SubscriptionRenewal\SubscriptionRenewalActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionRenewalActivityService
    {
        return app(SubscriptionRenewalActivityService::class);
    }

    public function test_can_record_note_call_and_follow_up(): void
    {
        $note = $this->service()->record(['activity_type' => 'NOTE', 'summary' => 'A note']);
        $this->assertSame('NOTE', $note->activity_type);

        $call = $this->service()->record([
            'activity_type' => 'CALL',
            'summary' => 'Follow up',
            'scheduled_at' => now()->addDay(),
        ]);
        $this->assertNotNull($call->scheduled_at);
    }

    public function test_manual_message_activity_does_not_send(): void
    {
        $activity = $this->service()->record([
            'activity_type' => SubscriptionRenewalActivity::TYPE_WHATSAPP_MANUAL,
            'summary' => 'WA manual note',
        ]);

        // It is only a record; no real send happens. Status stays PLANNED.
        $this->assertSame(SubscriptionRenewalActivity::STATUS_PLANNED, $activity->status);
    }

    public function test_complete_and_cancel(): void
    {
        $activity = $this->service()->record(['activity_type' => 'NOTE', 'summary' => 'x']);
        $this->assertSame('DONE', $this->service()->complete($activity)->status);

        $another = $this->service()->record(['activity_type' => 'NOTE', 'summary' => 'y']);
        $this->assertSame('CANCELLED', $this->service()->cancel($another)->status);
    }

    public function test_notes_are_redacted(): void
    {
        $activity = $this->service()->record([
            'activity_type' => 'NOTE',
            'summary' => 'x',
            'notes' => 'password: hunter2 secret info',
        ]);

        $this->assertStringContainsString('[REDACTED]', (string) $activity->notes);
        $this->assertStringNotContainsString('hunter2', (string) $activity->notes);
    }

    public function test_summary_reports_manual_only(): void
    {
        $this->service()->record(['activity_type' => 'NOTE', 'summary' => 'x']);
        $summary = $this->service()->summary();

        $this->assertSame(1, $summary['total_activities']);
        $this->assertTrue($summary['manual_only']);
        $this->assertTrue($summary['no_real_sending']);
    }
}
