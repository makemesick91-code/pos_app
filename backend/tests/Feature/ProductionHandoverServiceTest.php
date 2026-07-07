<?php

namespace Tests\Feature;

use App\Models\ProductionHandoverPackage;
use App\Models\User;
use App\Services\Handover\ProductionHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductionHandoverService
    {
        return app(ProductionHandoverService::class);
    }

    public function test_can_create_handover_package_with_checklist(): void
    {
        $package = $this->service()->create([
            'handover_reference' => 'HND-TEST-1',
            'candidate_commit' => '773f017',
            'candidate_tag' => 'sprint-18-go',
        ], User::factory()->platformAdmin()->create());

        $this->assertDatabaseHas('production_handover_packages', ['handover_reference' => 'HND-TEST-1']);
        $this->assertSame(ProductionHandoverPackage::STATUS_DRAFT, $package->status);
        $this->assertArrayHasKey('handover_docs', $package->checklist);
    }

    public function test_full_docs_contract_is_go(): void
    {
        $this->assertSame(ProductionHandoverService::DECISION_GO, $this->service()->evaluate()['decision']);
    }

    public function test_missing_required_docs_causes_no_go(): void
    {
        config(['production_handover.required_docs' => ['docs/handover/does-not-exist.md']]);

        $this->assertSame(ProductionHandoverService::DECISION_NO_GO, $this->service()->evaluate()['decision']);
    }

    public function test_mark_ready_transitions_conservatively(): void
    {
        $package = $this->service()->create([], User::factory()->platformAdmin()->create());

        $package = $this->service()->markReady($package);
        $this->assertSame(ProductionHandoverPackage::STATUS_READY, $package->status);

        // Only a READY package may be handed over.
        $package = $this->service()->markHandedOver($package, User::factory()->platformAdmin()->create());
        $this->assertSame(ProductionHandoverPackage::STATUS_HANDED_OVER, $package->status);
    }

    public function test_handed_over_requires_ready_first(): void
    {
        $package = $this->service()->create([], User::factory()->platformAdmin()->create());

        // DRAFT package must not jump straight to HANDED_OVER.
        $package = $this->service()->markHandedOver($package);
        $this->assertSame(ProductionHandoverPackage::STATUS_DRAFT, $package->status);
    }

    public function test_output_does_not_expose_secrets(): void
    {
        $json = json_encode($this->service()->evaluate());
        $this->assertStringNotContainsString('sk_live', (string) $json);
        $this->assertStringNotContainsString('APP_KEY', (string) $json);
    }
}
