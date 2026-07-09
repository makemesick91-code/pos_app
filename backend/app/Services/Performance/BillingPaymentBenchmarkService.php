<?php namespace App\Services\Performance; class BillingPaymentBenchmarkService { public function label(): string { return \App\Services\PaymentGateway\PaymentGatewayWebhookService::class; } }
