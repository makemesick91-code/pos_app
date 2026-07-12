<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBillingData;
use Tests\TestCase;

/**
 * UIX-5 — authenticated invoice-document delivery. Downloads are always behind
 * auth + the surface/tenant boundary, carry safe headers, derive the filename
 * from the canonical invoice number (never request input), and expose no public
 * URL or file/path parameter (UIX5-R007/R018). Access is audited (UIX5-R019).
 */
class Uix5InvoiceDownloadSecurityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBillingData;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant();
        $this->owner = $this->makeOwner($this->tenant);
    }

    public function test_guest_cannot_download_invoice(): void
    {
        $invoice = $this->makeInvoice($this->tenant);

        $this->get("/owner/billing/invoices/{$invoice->id}/download")
            ->assertRedirect('/owner/login');
    }

    public function test_download_carries_safe_headers(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-HDR-1']);

        $response = $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}/download")
            ->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('INV-HDR-1', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_filename_is_sanitised_no_path_traversal(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV/2026/07']);

        $disposition = (string) $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}/download")
            ->assertOk()
            ->headers->get('Content-Disposition');

        $this->assertStringNotContainsString('/', str_replace('inline; filename=', '', $disposition));
        $this->assertStringContainsString('INV-2026-07', $disposition);
    }

    public function test_owner_download_is_audited_without_sensitive_data(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-AUD-1']);

        $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}/download")
            ->assertOk();

        $log = AdminAuditLog::query()
            ->where('action', AdminAuditLog::ACTION_OWNER_INVOICE_DOWNLOADED)
            ->where('target_id', $invoice->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->owner->id, $log->actor_user_id);
        $encoded = json_encode($log->metadata);
        $this->assertStringNotContainsStringIgnoringCase('password', (string) $encoded);
        $this->assertStringNotContainsStringIgnoringCase('token', (string) $encoded);
    }

    public function test_download_document_does_not_leak_gateway_secrets(): void
    {
        $invoice = $this->makeInvoice($this->tenant, ['invoice_number' => 'INV-SEC-1']);
        $this->makeIntent($invoice, 'paid');

        $body = $this->actingAs($this->owner, 'owner')
            ->get("/owner/billing/invoices/{$invoice->id}/download")
            ->assertOk()
            ->getContent();

        // The document renders canonical amounts, not signature/payload hashes.
        $this->assertStringNotContainsString('signature_hash', $body);
        $this->assertStringNotContainsString('payload_hash', $body);
    }
}
