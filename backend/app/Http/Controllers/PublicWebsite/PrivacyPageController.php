<?php

namespace App\Http\Controllers\PublicWebsite;

use App\Http\Controllers\Controller;
use App\Models\PublicWebsitePage;
use Illuminate\Contracts\View\View;

/**
 * Sprint 21 — public privacy policy placeholder. Clearly marked as a readiness
 * template (not legal finality). No live analytics token.
 */
class PrivacyPageController extends Controller
{
    public function index(): View
    {
        $page = PublicWebsitePage::query()->where('page_key', PublicWebsitePage::KEY_PRIVACY)->first();

        return view('public-website.privacy', [
            'page' => $page,
            'seoTitle' => $page?->seo_title ?? 'Kebijakan Privasi — Aish POS Lite',
            'seoDescription' => $page?->seo_description ?? 'Kebijakan privasi Aish POS Lite (template kesiapan).',
        ]);
    }
}
