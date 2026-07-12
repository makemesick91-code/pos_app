<?php

namespace App\Http\Controllers\Owner;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * UIX-4 — tenant-scoped outlet (Store) console. Every query and lookup is
 * constrained to the authenticated owner's tenant; a foreign or unknown outlet
 * id resolves to 404, never another tenant's data (UIX4-R006/R007).
 */
class OwnerOutletController extends OwnerController
{
    public function index(Request $request): View
    {
        $context = $this->context();

        return $this->whenOperational($context, fn ($ctx) => view('owner.outlets.index', [
            'context' => $ctx,
            'outlets' => $this->read->outlets(
                $ctx,
                search: $request->query('q') !== null ? (string) $request->query('q') : null,
                status: (string) $request->query('status', 'all'),
                sort: (string) $request->query('sort', 'name'),
                direction: (string) $request->query('direction', 'asc'),
                perPage: (int) $request->query('per_page', 15),
            ),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', 'all'),
                'sort' => (string) $request->query('sort', 'name'),
                'direction' => (string) $request->query('direction', 'asc'),
            ],
        ]));
    }

    public function show(int $outlet): View
    {
        $context = $this->context();

        return $this->whenOperational($context, function ($ctx) use ($outlet) {
            $store = $this->read->findOutlet($ctx, $outlet);

            abort_if($store === null, 404);

            return view('owner.outlets.show', [
                'context' => $ctx,
                'detail' => $this->read->outletDetail($ctx, $store),
            ]);
        });
    }
}
