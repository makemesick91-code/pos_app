<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Services\SalesPipeline\SalesLeadActivityService;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLeadActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function lead(): SalesLead
    {
        return app(SalesLeadIntakeService::class)->create(['business_name' => 'X']);
    }

    private function service(): SalesLeadActivityService
    {
        return app(SalesLeadActivityService::class);
    }

    public function test_can_add_note_call_demo_follow_up(): void
    {
        $lead = $this->lead();

        foreach ([
            SalesLeadActivity::TYPE_NOTE,
            SalesLeadActivity::TYPE_CALL,
            SalesLeadActivity::TYPE_DEMO,
            SalesLeadActivity::TYPE_FOLLOW_UP,
        ] as $type) {
            $activity = $this->service()->add($lead, ['activity_type' => $type, 'summary' => $type]);
            $this->assertSame($type, $activity->activity_type);
        }

        $this->assertSame(4, SalesLeadActivity::query()->count());
    }

    public function test_manual_email_whatsapp_activity_does_not_send_real_message(): void
    {
        $lead = $this->lead();

        $wa = $this->service()->add($lead, ['activity_type' => SalesLeadActivity::TYPE_WHATSAPP_MANUAL, 'summary' => 'WA sent manually']);
        $email = $this->service()->add($lead, ['activity_type' => SalesLeadActivity::TYPE_EMAIL_MANUAL, 'summary' => 'email sent manually']);

        // Persisted as manual notes only; there is no real send side-effect.
        $this->assertDatabaseHas('sales_lead_activities', ['id' => $wa->id, 'activity_type' => 'WHATSAPP_MANUAL']);
        $this->assertDatabaseHas('sales_lead_activities', ['id' => $email->id, 'activity_type' => 'EMAIL_MANUAL']);
        $this->assertTrue($this->service()->summary()['manual_follow_up_only']);
    }

    public function test_can_complete_and_cancel_activity(): void
    {
        $lead = $this->lead();
        $a = $this->service()->add($lead, ['activity_type' => 'FOLLOW_UP', 'summary' => 'x', 'status' => 'PLANNED']);
        $b = $this->service()->add($lead, ['activity_type' => 'FOLLOW_UP', 'summary' => 'y', 'status' => 'PLANNED']);

        $this->service()->complete($a);
        $this->service()->cancel($b);

        $this->assertSame('DONE', $a->refresh()->status);
        $this->assertNotNull($a->completed_at);
        $this->assertSame('CANCELLED', $b->refresh()->status);
    }

    public function test_activity_summary_works(): void
    {
        $lead = $this->lead();
        $this->service()->add($lead, ['activity_type' => 'NOTE', 'summary' => 'x', 'status' => 'DONE']);
        $this->service()->add($lead, ['activity_type' => 'NOTE', 'summary' => 'y', 'status' => 'PLANNED']);

        $summary = $this->service()->summary();
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['done']);
        $this->assertSame(1, $summary['planned']);
        $this->assertSame('GO', $summary['decision']);
    }

    public function test_activity_notes_are_redacted(): void
    {
        $lead = $this->lead();
        $a = $this->service()->add($lead, [
            'activity_type' => 'NOTE',
            'summary' => 'ok',
            'notes' => 'token: ghp_secretvalue',
        ]);

        $this->assertStringNotContainsString('ghp_secretvalue', (string) $a->notes);
    }
}
