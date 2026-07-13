@extends('admin.layout')

@section('title', 'Detail insiden')

@section('content')
    <div class="breadcrumb"><a href="{{ route('admin.incidents.index') }}">Insiden</a> · {{ $incident['reference'] }}</div>
    <h1 class="page-title">{{ $incident['title'] ?: $incident['reference'] }}</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Severity</div>
            <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $incident['severity']])</div>
        </div>
        <div class="card">
            <div class="k">Status</div>
            <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $incident['status']])</div>
        </div>
        <div class="card">
            <div class="k">SLA</div>
            <div class="v" style="font-size:18px;">
                @if($incident['sla_breached'])<span class="badge badge-bad">Terlampaui</span>@else<span class="badge badge-ok">Dalam batas</span>@endif
            </div>
        </div>
        <div class="card">
            <div class="k">Bukti</div>
            <div class="v" style="font-size:18px;">{{ $incident['has_evidence'] ? 'Ada' : 'Tidak ada' }}</div>
            <div class="sub">Referensi bukti tidak ditampilkan</div>
        </div>
    </div>

    <div class="panel">
        <h2>Ringkasan insiden</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Referensi</dt><dd>{{ $incident['reference'] }}</dd>
                <dt>Area</dt><dd>{{ $incident['area'] ?? '—' }}</dd>
                <dt>Dampak</dt><dd>{{ $incident['impact'] ?: '—' }}</dd>
                <dt>Tenant terkait</dt><dd>{{ $incident['tenant_id'] !== null ? ('#'.$incident['tenant_id']) : 'Platform-wide' }}</dd>
                <dt>Risiko diterima</dt><dd>{{ $incident['is_accepted_risk'] ? 'Ya' : 'Tidak' }}</dd>
                <dt>Terdeteksi</dt><dd>{{ $incident['detected_at'] ?? '—' }}</dd>
                <dt>Dimulai</dt><dd>{{ $incident['started_at'] ?? '—' }}</dd>
                <dt>Batas SLA</dt><dd>{{ $incident['sla_due_at'] ?? '—' }}</dd>
                <dt>Diselesaikan</dt><dd>{{ $incident['resolved_at'] ?? '—' }}</dd>
                <dt>Ditutup</dt><dd>{{ $incident['closed_at'] ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <h2>Deskripsi (teredaksi)</h2>
        <div class="panel-body">
            <p style="white-space:pre-wrap;">{{ $incident['description'] ?: '—' }}</p>
        </div>
    </div>

    @if($incident['resolution_summary'])
        <div class="panel">
            <h2>Ringkasan penyelesaian</h2>
            <div class="panel-body">
                <p style="white-space:pre-wrap;">{{ $incident['resolution_summary'] }}</p>
            </div>
        </div>
    @endif

    <div class="panel">
        <div class="panel-body">
            <p style="color:var(--aish-text-secondary);font-size:13px;">
                Konsol hanya-baca. Perubahan status insiden dilakukan melalui layanan mutasi yang tergovernansi, bukan dari layar ini (UIX6-R015/R016).
            </p>
        </div>
    </div>
@endsection
