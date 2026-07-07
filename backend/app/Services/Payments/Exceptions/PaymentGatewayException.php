<?php

namespace App\Services\Payments\Exceptions;

use RuntimeException;

/**
 * Thrown when a QRIS provider is unknown, disabled, or not yet implemented for
 * live calls. Mapped to a 422/400 at the HTTP boundary — never a 500 that would
 * leak a stack trace.
 */
class PaymentGatewayException extends RuntimeException
{
    public static function providerNotAvailable(string $provider): self
    {
        return new self("QRIS provider [{$provider}] is not enabled or configured.");
    }

    public static function providerUnknown(string $provider): self
    {
        return new self("Unknown QRIS provider [{$provider}].");
    }

    public static function notImplemented(string $provider): self
    {
        return new self("Live QRIS calls for provider [{$provider}] are not implemented yet.");
    }
}
