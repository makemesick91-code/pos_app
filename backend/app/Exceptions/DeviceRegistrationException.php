<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backend-authoritative failure while registering or validating a device
 * (Sprint 10). Carries a stable machine code and any limit context so the
 * Android client can surface a blocked state. Rendered as JSON automatically.
 */
class DeviceRegistrationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_FORBIDDEN,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function limitReached(int $maxDevices, int $activeCount): self
    {
        return new self('Device limit reached', 'DEVICE_LIMIT_REACHED', Response::HTTP_FORBIDDEN, [
            'max_devices' => $maxDevices,
            'active_count' => $activeCount,
        ]);
    }

    public static function revoked(): self
    {
        return new self('Device has been revoked', 'DEVICE_REVOKED', Response::HTTP_FORBIDDEN);
    }

    public static function subscriptionInactive(string $status, ?string $reason): self
    {
        return new self('Subscription inactive', 'SUBSCRIPTION_INACTIVE', Response::HTTP_PAYMENT_REQUIRED, [
            'status' => $status,
            'reason' => $reason,
        ]);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->context), $this->status);
    }
}
