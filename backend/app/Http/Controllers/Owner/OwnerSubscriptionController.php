<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;

/**
 * UIX-4 — subscription / plan / billing visibility (read-only). Always
 * reachable, including when the tenant is suspended, so the owner can see their
 * plan and billing status and understand how to restore access. All values come
 * from canonical plan/billing services; nothing is recomputed and no payment
 * secret is surfaced (UIX4-R009/R016).
 */
class OwnerSubscriptionController extends OwnerController
{
    public function index(): View
    {
        $context = $this->context();

        return view('owner.subscription', [
            'context' => $context,
            'data' => $this->read->subscription($context),
        ]);
    }
}
