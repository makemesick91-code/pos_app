<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;

/**
 * UIX-4 — consolidated operational status (health, onboarding, sync/backlog),
 * read-only. Sourced from the canonical support/observability viewers. Never
 * exposes global infrastructure details, stack traces, hostnames, or another
 * tenant's data (UIX4 §20).
 */
class OwnerOperationsController extends OwnerController
{
    public function index(): View
    {
        $context = $this->context();

        return view('owner.operations', [
            'context' => $context,
            'data' => $this->read->operations($context),
        ]);
    }
}
