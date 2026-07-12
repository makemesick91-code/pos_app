<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;

/**
 * UIX-4 — the tenant owner dashboard. Always reachable (even when the tenant is
 * suspended) so the owner can always see their authoritative lifecycle status
 * and billing situation. All figures come from canonical services via
 * {@see OwnerConsoleReadService}; unavailable reads render as truthful empty
 * states, never fabricated zeros (UIX4-R010).
 */
class OwnerDashboardController extends OwnerController
{
    public function index(): View
    {
        $context = $this->context();

        return view('owner.dashboard', [
            'context' => $context,
            'data' => $this->read->dashboard($context),
        ]);
    }
}
