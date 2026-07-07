<?php

namespace Tests\Feature;

use App\Models\ProductionHandoverPackage;
use App\Models\ProductionHandoverSignoff;
use App\Services\Handover\ProductionHandoverService;
use App\Services\Handover\ProductionSignoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionSignoffServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionHandoverPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = app(ProductionHandoverService::class)->create([]);
    }

    private function service(): ProductionSignoffService
    {
        return app(ProductionSignoffService::class);
    }

    private function sign(string $role, string $decision): ProductionHandoverSignoff
    {
        return $this->service()->addSignoff($this->package, ['signer_role' => $role, 'decision' => $decision]);
    }

    public function test_can_add_signoff(): void
    {
        $signoff = $this->sign('OWNER', 'APPROVED');

        $this->assertDatabaseHas('production_handover_signoffs', ['id' => $signoff->id, 'signer_role' => 'OWNER']);
        $this->assertNotNull($signoff->signed_at);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sign('WIZARD', 'APPROVED');
    }

    public function test_all_required_roles_approved_is_go(): void
    {
        $this->sign('OWNER', 'APPROVED');
        $this->sign('TECHNICAL', 'APPROVED');
        $this->sign('SUPPORT', 'APPROVED');

        $this->assertSame(ProductionSignoffService::DECISION_GO, $this->service()->summary($this->package)['decision']);
    }

    public function test_rejected_signoff_causes_no_go(): void
    {
        $this->sign('OWNER', 'APPROVED');
        $this->sign('TECHNICAL', 'APPROVED');
        $this->sign('SUPPORT', 'REJECTED');

        $this->assertSame(ProductionSignoffService::DECISION_NO_GO, $this->service()->summary($this->package)['decision']);
    }

    public function test_approved_with_risk_causes_watch(): void
    {
        $this->sign('OWNER', 'APPROVED');
        $this->sign('TECHNICAL', 'APPROVED_WITH_RISK');
        $this->sign('SUPPORT', 'APPROVED');

        $this->assertSame(ProductionSignoffService::DECISION_WATCH, $this->service()->summary($this->package)['decision']);
    }

    public function test_signoff_records_are_append_only(): void
    {
        // A role signs REJECTED, then changes to APPROVED: both records survive.
        $this->sign('OWNER', 'REJECTED');
        $this->sign('OWNER', 'APPROVED');

        $this->assertSame(2, $this->package->signoffs()->count());
        // Latest wins for the OWNER role.
        $summary = $this->service()->summary($this->package);
        $this->assertSame(0, $summary['rejected']);
    }
}
