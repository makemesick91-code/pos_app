<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\QrisGateway;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Gateways\DuitkuQrisGateway;
use App\Services\Payments\Gateways\FakeQrisGateway;
use App\Services\Payments\Gateways\MidtransQrisGateway;
use App\Services\Payments\Gateways\XenditQrisGateway;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Resolves a QRIS provider by key from config/payment_gateway.php. A provider
 * must be both known and enabled; otherwise a PaymentGatewayException is thrown
 * (never a live call to a disabled/misconfigured gateway). Credentials are read
 * from config here and nowhere else.
 */
class QrisGatewayManager
{
    /** @var array<string, QrisGateway> */
    private array $resolved = [];

    public function __construct(private readonly Config $config) {}

    public function defaultProvider(): string
    {
        return (string) $this->config->get('payment_gateway.default_qris_provider', 'fake');
    }

    public function expiryMinutes(): int
    {
        return (int) $this->config->get('payment_gateway.qris_expiry_minutes', 15);
    }

    /**
     * Resolve a usable, enabled gateway or throw.
     */
    public function gateway(?string $provider = null): QrisGateway
    {
        $key = strtolower($provider ?: $this->defaultProvider());

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $config = $this->config->get("payment_gateway.providers.$key");

        if (! is_array($config)) {
            throw PaymentGatewayException::providerUnknown($key);
        }

        if (empty($config['enabled'])) {
            throw PaymentGatewayException::providerNotAvailable($key);
        }

        return $this->resolved[$key] = $this->build($key, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function build(string $key, array $config): QrisGateway
    {
        return match ($key) {
            'fake' => new FakeQrisGateway((string) ($config['webhook_secret'] ?? '')),
            'midtrans' => new MidtransQrisGateway($config),
            'xendit' => new XenditQrisGateway($config),
            'duitku' => new DuitkuQrisGateway($config),
            default => throw PaymentGatewayException::providerUnknown($key),
        };
    }
}
