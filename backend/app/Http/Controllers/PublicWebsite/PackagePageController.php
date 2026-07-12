<?php

namespace App\Http\Controllers\PublicWebsite;

use App\Http\Controllers\Controller;
use App\Models\PublicWebsitePage;
use App\Models\SaasPackageCatalog;
use Illuminate\Contracts\View\View;

/**
 * Sprint 21 — public packages preview. Shows governed, active commercial package
 * metadata (governance-aligned). It NEVER activates billing, NEVER creates a
 * subscription, and NEVER opens self-service signup. Pricing shown is governance
 * metadata only.
 */
class PackagePageController extends Controller
{
    public function index(): View
    {
        $packages = SaasPackageCatalog::query()->active()->orderBy('monthly_price')->get();
        $page = PublicWebsitePage::query()->where('page_key', PublicWebsitePage::KEY_PACKAGES)->first();

        return view('public-website.packages', [
            'packages' => $packages,
            'page' => $page,
            'seoTitle' => $page?->seo_title ?? 'Paket Pilot — Aish POS',
            'seoDescription' => $page?->seo_description
                ?? 'Pilihan paket pilot Aish POS untuk UMKM. Hubungi tim kami untuk penawaran dan aktivasi terbantu.',
        ]);
    }
}
