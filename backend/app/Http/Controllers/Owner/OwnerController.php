<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\OwnerConsole\OwnerConsoleReadService;
use App\Services\OwnerConsole\OwnerContext;
use App\Services\OwnerConsole\OwnerContextResolver;
use Illuminate\Contracts\View\View;

/**
 * UIX-4 — base for the authenticated Tenant Owner Web Console controllers. All
 * subclasses run behind the `tenant.owner.web` gate, so a context is always
 * resolvable; the tenant is derived server-side only (UIX4-R004).
 */
abstract class OwnerController extends Controller
{
    public function __construct(
        protected readonly OwnerContextResolver $resolver,
        protected readonly OwnerConsoleReadService $read,
    ) {}

    protected function context(): OwnerContext
    {
        return $this->resolver->require();
    }

    /**
     * Render a business-data page only when the tenant is operational; a
     * suspended / archived / blocked tenant instead sees a truthful restricted
     * status page and never its business data (UIX4-R011, rule 20 read-only).
     *
     * @param  callable(OwnerContext): View  $render
     */
    protected function whenOperational(OwnerContext $context, callable $render): View
    {
        if (! $context->operational()) {
            return view('owner.restricted', ['context' => $context]);
        }

        return $render($context);
    }
}
