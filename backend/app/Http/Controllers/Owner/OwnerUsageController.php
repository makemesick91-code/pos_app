<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;

/**
 * UIX-4 — plan entitlement & usage-vs-limit visibility (read-only). Usage counts
 * and limits are read from the canonical entitlement/usage services; the console
 * never recomputes a limit or a consumption figure (UIX4-R009).
 */
class OwnerUsageController extends OwnerController
{
    public function index(): View
    {
        $context = $this->context();

        return view('owner.usage', [
            'context' => $context,
            'data' => $this->read->usage($context),
        ]);
    }
}
