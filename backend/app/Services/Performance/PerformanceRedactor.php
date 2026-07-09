<?php

namespace App\Services\Performance;

class PerformanceRedactor
{
    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $safeKey = (string) $key;
                $out[$safeKey] = preg_match('/secret|token|password|payload|signature|email|phone|address/i', $safeKey)
                    ? '[redacted]'
                    : $this->redact($item);
            }
            return $out;
        }
        if (is_string($value)) {
            $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value) ?? $value;
            $value = preg_replace('/(sk_live_|server_key_|private_key_|AKIA)[A-Za-z0-9_\-]+/i', '[redacted-secret]', $value) ?? $value;
            return mb_substr($value, 0, 240);
        }
        return $value;
    }
}
