<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Contracts\PaymentGatewayProviderContract;
use App\Services\PaymentGateway\Providers\MockQrisPaymentGatewayProvider;

/**
 * Sprint 31 — resolves a configured payment gateway provider by key (PGW-R001).
 *
 * Only providers that are explicitly enabled in config may be resolved. Live
 * providers additionally require the master `live_gateway_enabled` switch, which
 * defaults to false — so in CI/tests only the deterministic mock is resolvable
 * and no real gateway is ever contacted (PGW-R002). A real provider class is only
 * instantiated when it has been wired (a later sprint); until then requesting a
 * live provider throws a governance error rather than guessing.
 */
class PaymentGatewayProviderManager
{
    public function __construct(
        private readonly MockQrisPaymentGatewayProvider $mock,
    ) {}

    public function default(): PaymentGatewayProviderContract
    {
        return $this->resolve((string) config('payment_gateway_governance.default_provider', 'mock'));
    }

    public function resolve(string $key): PaymentGatewayProviderContract
    {
        $providers = (array) config('payment_gateway_governance.providers', []);
        $definition = $providers[$key] ?? null;

        if ($definition === null) {
            throw new PaymentGatewayException('GATEWAY_UNKNOWN_PROVIDER', "Unknown payment gateway provider '{$key}'.");
        }

        if (! (bool) ($definition['enabled'] ?? false)) {
            throw new PaymentGatewayException('GATEWAY_PROVIDER_DISABLED', "Payment gateway provider '{$key}' is disabled.");
        }

        if ($key === 'mock') {
            return $this->mock;
        }

        // A live provider requires the master switch AND a wired implementation.
        if ((bool) ($definition['live'] ?? false) && ! (bool) config('payment_gateway_governance.live_gateway_enabled', false)) {
            throw new PaymentGatewayException(
                'GATEWAY_LIVE_DISABLED',
                "Live payment gateway '{$key}' is not enabled; only the mock provider is available.",
            );
        }

        // No real provider is wired in the Sprint 31 foundation.
        throw new PaymentGatewayException(
            'GATEWAY_PROVIDER_NOT_WIRED',
            "Payment gateway provider '{$key}' has no wired implementation in this build.",
        );
    }

    /**
     * @return list<string>
     */
    public function channelsFor(string $key): array
    {
        $providers = (array) config('payment_gateway_governance.providers', []);

        return array_values((array) ($providers[$key]['channels'] ?? []));
    }
}
