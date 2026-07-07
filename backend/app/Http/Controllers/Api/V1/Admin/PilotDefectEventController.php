<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PilotDefectEventResource;
use App\Models\PilotDefect;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 17 — read-only, append-only lifecycle event history for a pilot defect.
 * Platform admin only. Events are never mutated or deleted.
 */
class PilotDefectEventController extends Controller
{
    public function index(PilotDefect $defect): AnonymousResourceCollection
    {
        return PilotDefectEventResource::collection(
            $defect->events()->orderBy('id')->get(),
        );
    }
}
