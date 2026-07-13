<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\ProductionIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UIX-6 — the Platform Admin incident console reads the canonical
 * ProductionIncident lifecycle verbatim, is read-only (no mutation route), and
 * redacts free text + never renders raw evidence payloads
 * (UIX6-R009/R014/R015/R016/R019).
 */
class Uix6IncidentConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function incident(array $overrides = []): ProductionIncident
    {
        return ProductionIncident::query()->create(array_merge([
            'incident_reference' => 'INC-UIX6-'.uniqid(),
            'area' => 'BACKEND_API',
            'severity' => ProductionIncident::SEVERITY_P1,
            'status' => ProductionIncident::STATUS_INVESTIGATING,
            'impact' => 'Sebagian tenant',
            'title' => 'API lambat',
            'detected_at' => now(),
        ], $overrides));
    }

    public function test_incident_list_and_detail_render(): void
    {
        $incident = $this->incident(['title' => 'Gateway pembayaran gagal']);

        $this->actingAs($this->admin, 'web')
            ->get('/admin/incidents')
            ->assertOk()
            ->assertSee($incident->incident_reference);

        $this->actingAs($this->admin, 'web')
            ->get("/admin/incidents/{$incident->id}")
            ->assertOk()
            ->assertSee('Gateway pembayaran gagal');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_ADMIN_INCIDENT_VIEWED,
            'target_type' => AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            'target_id' => $incident->id,
        ]);
    }

    public function test_unknown_incident_returns_404(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/incidents/999999')
            ->assertNotFound();
    }

    public function test_incident_detail_redacts_secrets_and_hides_evidence_payload(): void
    {
        $incident = $this->incident([
            'description' => 'Owner emailed ops@merchant.example.com with token sk_live_ABC123SECRET',
            'evidence_reference' => 's3://private-bucket/incident/secret-path.log',
        ]);

        $response = $this->actingAs($this->admin, 'web')->get("/admin/incidents/{$incident->id}")->assertOk();

        // Free-text secrets are redacted (UIX6-R009/R019).
        $response->assertDontSee('ops@merchant.example.com');
        $response->assertDontSee('sk_live_ABC123SECRET');
        // Evidence is presence-not-payload; the raw storage path never appears.
        $response->assertDontSee('s3://private-bucket/incident/secret-path.log');
        $response->assertSee('Ada'); // "Bukti: Ada"
    }

    public function test_incident_console_exposes_no_mutation_route(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/incidents')) {
                $this->assertSame(['GET', 'HEAD'], array_values(array_intersect(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())));
            }
            if (str_starts_with($route->uri(), 'admin/support') || str_starts_with($route->uri(), 'admin/observability')) {
                $this->assertNotContains('POST', $route->methods());
                $this->assertNotContains('PATCH', $route->methods());
                $this->assertNotContains('DELETE', $route->methods());
            }
        }
    }
}
