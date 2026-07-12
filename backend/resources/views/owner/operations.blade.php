@extends('owner.layout')

@section('title', 'Operasional')

@section('content')
    <div class="breadcrumb">Operasional</div>
    <h1 class="page-title">Status operasional</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Kesehatan</div>
            @if($data['health']['available'] ?? false)
                <div class="v" style="font-size:18px;">{{ ucfirst($data['health']['health_status'] ?? '—') }}</div>
                <div class="sub">{{ implode(', ', $data['health']['reason_codes'] ?? []) }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Onboarding</div>
            @if($data['onboarding']['available'] ?? false)
                <div class="v" style="font-size:18px;">{{ $data['onboarding']['latest']['status'] ?? 'Belum ada' }}</div>
                <div class="sub">{{ (int) ($data['onboarding']['run_count'] ?? 0) }} proses</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Sinkronisasi gagal</div>
            @if($data['sync_failures']['available'] ?? false)
                <div class="v">{{ (int) ($data['sync_failures']['failed_batch_count'] ?? 0) }}</div>
                <div class="sub">batch · {{ (int) ($data['sync_failures']['failed_item_count'] ?? 0) }} item</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Perangkat tersinkron</div>
            @if($data['sync']['available'] ?? false)
                <div class="v">{{ (int) ($data['sync']['device_count'] ?? 0) }}</div>
                <div class="sub">{{ (int) ($data['sync']['sync_batch_count'] ?? 0) }} batch sinkron</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <p style="color:var(--aish-text-secondary);font-size:13px;">
                Status ini hanya mencakup bisnis Anda. Detail infrastruktur platform tidak ditampilkan.
            </p>
        </div>
    </div>
@endsection
