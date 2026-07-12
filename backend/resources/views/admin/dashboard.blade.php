@extends('admin.layout')

@section('title', 'Dashboard')

@php
    /** @var array<string, mixed> $metrics */
    $money = fn ($v) => 'Rp ' . number_format((int) $v, 0, ',', '.');
@endphp

@section('content')
    <div class="breadcrumb"><a href="{{ route('admin.dashboard') }}">Control Center</a> · Dashboard</div>
    <h1 class="page-title">SaaS Control Center</h1>
    <p class="brand-sub" style="color: var(--aish-text-secondary); margin-top: -8px;">
        Ringkasan runtime. Diperbarui {{ \Illuminate\Support\Carbon::parse($metrics['generated_at'])->diffForHumans() }}.
    </p>

    <section aria-label="Metrik tenant" class="cards" style="margin-top: var(--aish-space-lg);">
        @php $t = $metrics['tenants']; @endphp
        <div class="card">
            <div class="k">Total Tenant</div>
            @if ($t['available'])
                <div class="v aish-num">{{ number_format($t['total']) }}</div>
                <div class="sub">{{ $t['active'] }} aktif · {{ $t['suspended'] }} suspended · {{ $t['inactive'] }} nonaktif</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $tr = $metrics['trials']; @endphp
        <div class="card">
            <div class="k">Trial Aktif</div>
            @if ($tr['available'])
                <div class="v aish-num">{{ number_format($tr['trials_active']) }}</div>
                <div class="sub">{{ $tr['trials_expired'] }} kadaluarsa · {{ $tr['trials_total'] }} total trial</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $b = $metrics['billing']; @endphp
        <div class="card">
            <div class="k">Invoice Perlu Perhatian</div>
            @if ($b['available'])
                <div class="v aish-num">{{ number_format($b['attention_invoices']) }}</div>
                <div class="sub">{{ $b['total_invoices'] }} invoice · {{ $money($b['total_amount']) }}</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $s = $metrics['settlement']; @endphp
        <div class="card">
            <div class="k">Settlement</div>
            @if ($s['available'])
                <div class="v aish-num">{{ number_format($s['settled_intents']) }}</div>
                <div class="sub">{{ $s['open_intents'] }} open · {{ $money($s['settled_amount']) }} settled</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $d = $metrics['devices']; @endphp
        <div class="card">
            <div class="k">Perangkat Aktif</div>
            @if ($d['available'])
                <div class="v aish-num">{{ number_format($d['active']) }}</div>
                <div class="sub">{{ $d['revoked'] }} dicabut · {{ $d['total'] }} total</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $o = $metrics['outlets']; @endphp
        <div class="card">
            <div class="k">Outlet</div>
            @if ($o['available'])
                <div class="v aish-num">{{ number_format($o['total']) }}</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>

        @php $sup = $metrics['support']; @endphp
        <div class="card">
            <div class="k">Insiden Terbuka</div>
            @if ($sup['available'])
                <div class="v aish-num">{{ number_format($sup['open_incidents']) }}</div>
            @else
                <div class="v unavailable">Tidak tersedia</div>
            @endif
        </div>
    </section>

    <section class="panel" aria-label="Kesehatan operasional">
        <h2>Kesehatan Operasional</h2>
        <div class="panel-body">
            <dl class="kv">
                @php $q = $metrics['queue']; $h = $metrics['health']; @endphp
                <dt>Status layanan</dt>
                <dd>
                    @if ($h['available'])
                        <span class="aish-badge aish-badge--{{ $h['status'] === 'healthy' ? 'success' : ($h['status'] === 'unknown' ? 'info' : 'warning') }}">{{ strtoupper($h['status']) }}</span>
                        @if (!empty($h['reason_codes']))
                            <span class="sub">{{ implode(', ', $h['reason_codes']) }}</span>
                        @endif
                    @else
                        <span class="unavailable">Tidak tersedia</span>
                    @endif
                </dd>

                <dt>Antrian (queue)</dt>
                <dd>
                    @if ($q['available'])
                        <span class="aish-badge aish-badge--{{ $q['status'] === 'healthy' ? 'success' : ($q['status'] === 'unknown' ? 'info' : 'warning') }}">{{ strtoupper($q['status']) }}</span>
                        <span class="sub aish-num">{{ $q['pending_jobs'] }} pending · {{ $q['failed_jobs'] }} failed</span>
                    @else
                        <span class="unavailable">Tidak tersedia</span>
                    @endif
                </dd>
            </dl>
            <p class="sub" style="margin-top: var(--aish-space-lg);">
                Portal ini read-only. Tindakan tenant (suspend, aktivasi ulang, ubah paket) dikelola melalui
                layanan tergovernansi dan tidak tersedia di UIX-3.
            </p>
        </div>
    </section>
@endsection
