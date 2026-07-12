@extends('owner.layout')

@section('title', 'Detail Perangkat')

@section('content')
    <div class="breadcrumb"><a href="{{ route('owner.devices.index') }}">Perangkat</a> · {{ $device['device_label'] ?? ('#'.$device['id']) }}</div>
    <h1 class="page-title">Detail perangkat</h1>

    <div class="panel">
        <h2>Status aktivasi</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Label</dt><dd>{{ $device['device_label'] ?? '—' }}</dd>
                <dt>Status</dt>
                <dd>
                    <span class="badge {{ ($device['status'] ?? '') === 'activated' ? 'badge-ok' : (($device['status'] ?? '') === 'revoked' ? 'badge-bad' : 'badge-neutral') }}">
                        {{ $device['status'] ?? '—' }}
                    </span>
                </dd>
                <dt>Diaktifkan pada</dt><dd>{{ $device['activated_at'] ?? '—' }}</dd>
                <dt>Dicabut pada</dt><dd>{{ $device['revoked_at'] ?? '—' }}</dd>
                <dt>Kedaluwarsa</dt><dd>{{ $device['expires_at'] ?? '—' }}</dd>
                <dt>Terakhir terlihat</dt><dd>{{ $device['last_seen_at'] ?? '—' }}</dd>
            </dl>
            <p class="sub" style="margin-top:var(--aish-space-md);color:var(--aish-text-secondary);font-size:12px;">
                Token aktivasi dan sidik jari perangkat tidak pernah ditampilkan demi keamanan.
            </p>
        </div>
    </div>
@endsection
