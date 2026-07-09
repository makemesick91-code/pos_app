<?php

namespace App\Services\Performance;

class PerformanceFixtureService
{
    public function __construct(private readonly PerformanceRedactor $redactor) {}

    public function build(string $profile, bool $execute = false): array
    {
        $config = $this->profile($profile);
        return $this->redactor->redact([
            'profile' => $profile,
            'mode' => $execute ? 'execute' : 'dry_run',
            'fixture_key' => 'sprint38-'.$profile,
            'would_create' => [
                'tenants' => $config['tenant_count'],
                'products' => $config['product_count'],
                'pos_transactions' => $config['pos_transaction_count'],
                'android_sync_items' => $config['android_sync_item_count'],
                'import_rows' => $config['import_row_count'],
            ],
            'records_created' => $execute ? 0 : 0,
            'cleanup_scope' => 'benchmark_key prefix sprint38 only',
        ]);
    }

    public function profile(string $profile): array
    {
        $profiles = (array) config('performance_governance.profiles', []);
        if (! array_key_exists($profile, $profiles)) {
            throw new \InvalidArgumentException("Unknown performance profile [{$profile}].");
        }
        return $profiles[$profile];
    }
}
