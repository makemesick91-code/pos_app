<?php

namespace App\Http\Controllers\PublicWebsite;

use App\Http\Controllers\Controller;
use App\Models\LandingPageVersion;
use App\Models\PublicWebsitePage;
use Illuminate\Contracts\View\View;

/**
 * Sprint 21 — public landing page. Unauthenticated, read-only, fast, secret-free.
 * Renders the published landing page version (falling back to safe defaults). This
 * page NEVER creates a tenant/user/subscription/device and exposes no admin data.
 */
class LandingPageController extends Controller
{
    public function index(): View
    {
        $version = LandingPageVersion::query()->published()->latest('id')->first();
        $page = PublicWebsitePage::query()->where('page_key', PublicWebsitePage::KEY_HOME)->first();

        return view('public-website.home', [
            'version' => $version,
            'page' => $page,
            'seoTitle' => $page?->seo_title ?? 'Aish POS Lite — POS Android SaaS untuk UMKM',
            'seoDescription' => $page?->seo_description
                ?? 'Aish POS Lite: kasir Android ringan untuk UMKM — QRIS, tunai, mode offline, cetak struk, stok sederhana, dan laporan harian.',
        ]);
    }

    public function thankYou(): View
    {
        return view('public-website.thank-you', [
            'seoTitle' => 'Terima kasih — Aish POS Lite',
            'seoDescription' => 'Terima kasih atas minat Anda pada Aish POS Lite. Tim kami akan menghubungi Anda.',
        ]);
    }
}
