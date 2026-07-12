<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ControlCenterMetricsService;
use Illuminate\Contracts\View\View;

/**
 * UIX-3 — SaaS Control Center landing dashboard (GET /admin).
 *
 * Read-only. All figures come from {@see ControlCenterMetricsService}, which
 * reuses the existing governed summary services and reports unavailable groups
 * honestly. This controller performs no business calculation of its own.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private readonly ControlCenterMetricsService $metrics) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => $this->metrics->overview(),
        ]);
    }
}
