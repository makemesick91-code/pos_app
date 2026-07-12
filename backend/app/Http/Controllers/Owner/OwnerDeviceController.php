<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * UIX-4 — tenant-scoped device activation console (read-only). Only safe device
 * columns are surfaced; the activation token hash and device fingerprint hash
 * are never exposed (UIX4-R016). Lookups are constrained to the owner's tenant
 * (UIX4-R006/R007).
 */
class OwnerDeviceController extends OwnerController
{
    public function index(Request $request): View
    {
        $context = $this->context();

        return $this->whenOperational($context, fn ($ctx) => view('owner.devices.index', [
            'context' => $ctx,
            'devices' => $this->read->devices(
                $ctx,
                search: $request->query('q') !== null ? (string) $request->query('q') : null,
                status: (string) $request->query('status', 'all'),
                perPage: (int) $request->query('per_page', 15),
            ),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', 'all'),
            ],
        ]));
    }

    public function show(int $device): View
    {
        $context = $this->context();

        return $this->whenOperational($context, function ($ctx) use ($device) {
            $activation = $this->read->findDevice($ctx, $device);

            abort_if($activation === null, 404);

            return view('owner.devices.show', [
                'context' => $ctx,
                'device' => $activation->toSafeArray(),
            ]);
        });
    }
}
