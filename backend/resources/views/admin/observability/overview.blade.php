@extends('admin.layout')

@section('title', 'Observabilitas')

@php $h = $data['health'] ?? []; @endphp

@section('content')
    <div class="breadcrumb">Observabilitas</div>
    <h1 class="page-title">Observabilitas platform</h1>

    @if($h['available'] ?? false)
        @if($h['snapshot_missing'] ?? false)
            <div class="notice">
                Belum ada snapshot kesehatan yang tercatat. Status komponen di bawah berasal dari pemeriksaan langsung;
                kesegaran historis <strong>tidak tersedia</strong>.
            </div>
        @elseif($h['snapshot_stale'] ?? false)
            <div class="notice">
                Snapshot kesehatan terakhir sudah usang (per {{ $h['snapshot_as_of'] }}). Data mungkin tidak mencerminkan kondisi terkini.
            </div>
        @endif
    @endif

    <div class="cards">
        <div class="card">
            <div class="k">Kesehatan aplikasi</div>
            @if($h['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $h['status'] ?? null])</div>
                <div class="sub">Pemeriksaan langsung: {{ $h['checked_at'] ?? '—' }}</div>
                @if(($h['snapshot_missing'] ?? false) || ($h['snapshot_stale'] ?? false))
                    <div class="sub">Snapshot historis: {{ ($h['snapshot_missing'] ?? false) ? 'belum ada' : 'usang' }}</div>
                @endif
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Infrastruktur</div>
            @if($h['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $h['components']['infrastructure']['display_status'] ?? 'unknown'])</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Antrian (queue)</div>
            @if($h['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $h['components']['queue']['display_status'] ?? 'unknown'])</div>
                @if($h['components']['queue']['known'] ?? false)
                    <div class="sub aish-num">{{ (int) ($h['components']['queue']['pending_jobs'] ?? 0) }} tertunda · {{ (int) ($h['components']['queue']['failed_jobs'] ?? 0) }} gagal</div>
                @else
                    <div class="sub">Tabel job tidak tersedia</div>
                @endif
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Penjadwal (scheduler)</div>
            @if($h['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $h['components']['scheduler']['display_status'] ?? 'unknown'])</div>
                @unless($h['components']['scheduler']['known'] ?? false)
                    <div class="sub">Belum ada run tercatat — status tidak diketahui, bukan sehat</div>
                @endunless
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Ringkasan operasional</h2>
        <div class="panel-body">
            @php $m = $data['metrics'] ?? []; @endphp
            <dl class="kv">
                <dt>Tenant terdegradasi</dt>
                <dd>@if($data['tenants']['available'] ?? false)<span class="aish-num">{{ (int) ($data['tenants']['degraded'] ?? 0) }}</span>@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
                <dt>Anomali terbuka</dt>
                <dd>@if($m['available'] ?? false)<span class="aish-num">{{ (int) ($m['open_anomalies_total'] ?? 0) }}</span>@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
                <dt>Saran alert terbuka</dt>
                <dd>@if($m['available'] ?? false)<span class="aish-num">{{ (int) ($m['open_alert_suggestions'] ?? 0) }}</span>@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
                <dt>Insiden dukungan terbuka</dt>
                <dd>@if($m['available'] ?? false)<span class="aish-num">{{ (int) ($m['open_support_incidents'] ?? 0) }}</span>@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <h2>Anomali terdeteksi</h2>
        <div class="panel-body">
            @php $anomalies = $data['anomalies']['items'] ?? []; @endphp
            @if(! ($data['anomalies']['available'] ?? false))
                <p class="unavailable">Tidak tersedia</p>
            @elseif(count($anomalies) === 0)
                <div class="empty">Tidak ada anomali terdeteksi.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Kunci</th>
                                <th scope="col">Kategori</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Ringkasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($anomalies, 0, 50) as $a)
                                <tr>
                                    <td>{{ $a['anomaly_key'] ?? ($a['key'] ?? '—') }}</td>
                                    <td>{{ $a['category'] ?? '—' }}</td>
                                    <td>@include('support.partials.status-badge', ['status' => $a['severity'] ?? null])</td>
                                    <td>{{ $a['summary_safe'] ?? ($a['summary'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <p style="color:var(--aish-text-secondary);font-size:13px;">
                Konsol ini hanya-baca dan tidak mengekspos log mentah, jejak tumpukan, kredensial, atau identitas infrastruktur privat.
                Status berasal dari layanan observabilitas kanonik Sprint 36.
            </p>
        </div>
    </div>
@endsection
