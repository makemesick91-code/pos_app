<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 31 — static guard that the gateway surface holds no hardcoded secret and
 * that the redaction contract is intact (PGW-R011/R016). The mock signing key is
 * a documented, non-secret deterministic test constant and is allowlisted.
 */
class PaymentGatewaySecurityScanTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function gatewayFiles(): array
    {
        $roots = [
            base_path('app/Services/PaymentGateway'),
            base_path('app/Console/Commands'),
            base_path('app/Http/Controllers/Api/V1/Admin'),
            base_path('config/payment_gateway_governance.php'),
        ];

        $files = [];
        foreach ($roots as $root) {
            if (is_file($root)) {
                $files[] = $root;

                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php') && str_contains($file->getPathname(), 'PaymentGateway')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_no_live_secret_like_literals_in_gateway_code(): void
    {
        foreach ($this->gatewayFiles() as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/sk_live_[A-Za-z0-9]/', $contents, "Secret-like literal in {$file}");
            $this->assertDoesNotMatchRegularExpression('/xnd_[A-Za-z0-9]{10}/', $contents, "Secret-like literal in {$file}");
            $this->assertDoesNotMatchRegularExpression('/SB-Mid-server-[A-Za-z0-9]/', $contents, "Secret-like literal in {$file}");
        }
    }

    public function test_redactor_drops_signature_and_secret_keys(): void
    {
        $redactor = app(\App\Services\PaymentGateway\PaymentGatewayRedactor::class);
        $clean = $redactor->sanitize([
            'ok' => 'keep',
            'signature' => 'sig-value',
            'server_key' => 'SB-xxx',
            'raw_payload' => 'blob',
            'card_number' => '4111111111111111',
        ]);

        $this->assertArrayHasKey('ok', $clean);
        $this->assertArrayNotHasKey('signature', $clean);
        $this->assertArrayNotHasKey('server_key', $clean);
        $this->assertArrayNotHasKey('raw_payload', $clean);
        $this->assertArrayNotHasKey('card_number', $clean);
    }

    public function test_signature_hash_is_not_reversible_and_truncated(): void
    {
        $redactor = app(\App\Services\PaymentGateway\PaymentGatewayRedactor::class);
        $hash = $redactor->signatureHash('super-secret-signature');

        $this->assertNotNull($hash);
        $this->assertStringNotContainsString('super-secret-signature', (string) $hash);
        $this->assertSame(32, strlen((string) $hash));
    }

    public function test_go_no_go_json_output_has_no_secret(): void
    {
        $report = app(\App\Services\PaymentGateway\PaymentGatewayGoNoGoService::class)->evaluate();
        $json = (string) json_encode($report);

        $this->assertDoesNotMatchRegularExpression('/sk_live_|xnd_[A-Za-z0-9]{10}|password|server_key/i', $json);
    }
}
