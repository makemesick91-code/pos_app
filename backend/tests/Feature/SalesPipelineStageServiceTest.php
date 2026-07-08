<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Models\SalesPipelineStage;
use App\Services\SalesPipeline\SalesLeadIntakeService;
use App\Services\SalesPipeline\SalesPipelineStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SalesPipelineStageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SalesPipelineStageService
    {
        return app(SalesPipelineStageService::class);
    }

    public function test_ensure_defaults_creates_canonical_stages(): void
    {
        $this->service()->ensureDefaults();

        foreach ((array) config('sales_pipeline.canonical_stages') as $code) {
            $this->assertDatabaseHas('sales_pipeline_stages', ['stage_code' => $code]);
        }

        // Idempotent.
        $this->service()->ensureDefaults();
        $this->assertSame(
            count((array) config('sales_pipeline.default_stage_definitions')),
            SalesPipelineStage::query()->count(),
        );
    }

    public function test_stage_transition_works(): void
    {
        $this->service()->ensureDefaults();
        $lead = app(SalesLeadIntakeService::class)->create(['business_name' => 'X']);

        $lead = $this->service()->transitionLead($lead, 'CONTACTED');

        $this->assertSame(SalesLead::STATUS_CONTACTED, $lead->status);
        $this->assertNotNull($lead->pipeline_stage_id);
    }

    public function test_terminal_stage_behavior_is_conservative(): void
    {
        $this->service()->ensureDefaults();

        $won = SalesPipelineStage::query()->where('stage_code', 'WON_READY_FOR_ONBOARDING')->first();
        $this->assertTrue((bool) $won->is_terminal);

        $lost = SalesPipelineStage::query()->where('stage_code', 'LOST')->first();
        $this->assertTrue((bool) $lost->is_terminal);
    }

    public function test_invalid_stage_is_rejected(): void
    {
        $this->service()->ensureDefaults();
        $lead = app(SalesLeadIntakeService::class)->create(['business_name' => 'X']);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transitionLead($lead, 'NOT_A_STAGE');
    }
}
