@extends('admin.layout')

@section('title', 'Detail Tenant')

@php
    $badge = function (?string $status): string {
        $s = strtoupper((string) $status);
        return match (true) {
            in_array($s, ['ACTIVE', 'HEALTHY'], true) => 'success',
            in_array($s, ['GRACE', 'PAST_DUE', 'ONBOARDING', 'DEGRADED', 'WARNING'], true) => 'warning',
            in_array($s, ['SUSPENDED', 'CANCELLED', 'ARCHIVED', 'INACTIVE', 'CRITICAL', 'BLOCKED'], true) => 'danger',
            default => 'info',
        };
    };
    // Recursive, fully-escaped renderer for already-redacted summary arrays.
    $render = function ($data) use (&$render) {
        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }
        if ($data === null) {
            return '<span class="unavailable">—</span>';
        }
        if (! is_array($data)) {
            return e((string) $data);
        }
        if ($data === []) {
            return '<span class="unavailable">—</span>';
        }
        $html = '<dl class="kv">';
        foreach ($data as $k => $v) {
            $html .= '<dt>' . e((string) $k) . '</dt><dd>' . $render($v) . '</dd>';
        }
        return $html . '</dl>';
    };
@endphp

@section('content')
    <div class="breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Control Center</a> ·
        <a href="{{ route('admin.tenants.index') }}">Tenant</a> ·
        {{ $tenant->name }}
    </div>
    <h1 class="page-title">{{ $tenant->name }}</h1>

    <div class="panel" style="margin-top:0;">
        <h2>Identitas</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Kode</dt><dd>{{ $tenant->code }}</dd>
                <dt>Nama</dt><dd>{{ $tenant->name }}</dd>
                <dt>Pemilik</dt><dd>{{ $tenant->owner_name ?? '—' }}</dd>
                <dt>Jenis usaha</dt><dd>{{ $tenant->business_type ?? '—' }}</dd>
                <dt>Status (kolom)</dt><dd><span class="aish-badge aish-badge--{{ $badge($tenant->status) }}">{{ strtoupper($tenant->status) }}</span></dd>
                <dt>Outlet</dt><dd class="aish-num">{{ (int) ($tenant->stores_count ?? 0) }}</dd>
                <dt>Perangkat aktif</dt><dd class="aish-num">{{ (int) ($tenant->devices_active_count ?? 0) }}</dd>
                <dt>Dibuat</dt><dd>{{ optional($tenant->created_at)->toDayDateTimeString() ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <h2>Status Lifecycle (otoritatif)</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Status</dt>
                <dd>
                    <span class="aish-badge aish-badge--{{ $badge($lifecycle['tenant_status']) }}">{{ strtoupper((string) $lifecycle['tenant_status']) }}</span>
                    <span class="aish-badge aish-badge--{{ $lifecycle['allowed'] ? 'success' : 'danger' }}">{{ $lifecycle['allowed'] ? 'DIIZINKAN' : 'DIBLOKIR' }}</span>
                </dd>
                <dt>Sumber keputusan</dt><dd>{{ $lifecycle['source'] }}</dd>
                <dt>Alasan</dt><dd>{{ $lifecycle['reason'] ?? '—' }}</dd>
                <dt>Suspensi manual</dt><dd>{{ $lifecycle['manually_suspended'] ? 'Ya' : 'Tidak' }}</dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <h2>Langganan</h2>
        <div class="panel-body">
            @if ($subscription['available'])
                <dl class="kv">
                    <dt>Paket</dt><dd>{{ $subscription['plan_name'] ?? '—' }} <span class="sub">{{ $subscription['plan_code'] ?? '' }}</span></dd>
                    <dt>Status langganan</dt><dd><span class="aish-badge aish-badge--{{ $badge($subscription['status'] ?? null) }}">{{ strtoupper((string) ($subscription['status'] ?? 'UNKNOWN')) }}</span></dd>
                    <dt>Mulai</dt><dd>{{ optional($subscription['starts_at'] ?? null)->toDateString() ?? '—' }}</dd>
                    <dt>Berakhir</dt><dd>{{ optional($subscription['ends_at'] ?? null)->toDateString() ?? '—' }}</dd>
                    <dt>Trial berakhir</dt><dd>{{ optional($subscription['trial_ends_at'] ?? null)->toDateString() ?? '—' }}</dd>
                    <dt>Maks perangkat</dt><dd class="aish-num">{{ $subscription['max_devices'] ?? '—' }}</dd>
                    <dt>Maks outlet</dt><dd class="aish-num">{{ $subscription['max_stores'] ?? '—' }}</dd>
                </dl>
            @else
                <p class="unavailable">Ringkasan langganan tidak tersedia.</p>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Kesehatan &amp; Operasional</h2>
        <div class="panel-body">
            @if ($overview['available'])
                <dl class="kv">
                    <dt>Status kesehatan</dt>
                    <dd>
                        <span class="aish-badge aish-badge--{{ $badge($overview['health_status'] ?? null) }}">{{ strtoupper((string) ($overview['health_status'] ?? 'UNKNOWN')) }}</span>
                    </dd>
                    <dt>Kode alasan</dt>
                    <dd>{{ !empty($overview['reason_codes']) ? implode(', ', $overview['reason_codes']) : '—' }}</dd>
                </dl>

                @foreach (($overview['dimensions'] ?? []) as $name => $dimension)
                    <details style="margin-top: var(--aish-space-md);">
                        <summary style="cursor:pointer;font-weight:600;">{{ ucfirst(str_replace('_', ' ', (string) $name)) }}</summary>
                        <div style="padding: var(--aish-space-sm) 0;">{!! $render($dimension) !!}</div>
                    </details>
                @endforeach
            @else
                <p class="unavailable">Ringkasan kesehatan tidak tersedia.</p>
            @endif

            <p class="sub" style="margin-top: var(--aish-space-lg);">
                Tampilan read-only. Perubahan status atau langganan tenant hanya dapat dilakukan melalui
                layanan tergovernansi (di luar cakupan UIX-3). Akses ini tercatat pada audit trail.
            </p>
        </div>
    </div>
@endsection
