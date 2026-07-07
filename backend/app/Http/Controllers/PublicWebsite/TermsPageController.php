<?php

namespace App\Http\Controllers\PublicWebsite;

use App\Http\Controllers\Controller;
use App\Models\PublicWebsitePage;
use Illuminate\Contracts\View\View;

/**
 * Sprint 21 — public terms placeholder. Clearly marked as a readiness template
 * (not legal finality).
 */
class TermsPageController extends Controller
{
    public function index(): View
    {
        $page = PublicWebsitePage::query()->where('page_key', PublicWebsitePage::KEY_TERMS)->first();

        return view('public-website.terms', [
            'page' => $page,
            'seoTitle' => $page?->seo_title ?? 'Ketentuan Layanan — Aish POS Lite',
            'seoDescription' => $page?->seo_description ?? 'Ketentuan layanan Aish POS Lite (template kesiapan).',
        ]);
    }
}
