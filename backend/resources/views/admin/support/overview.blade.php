@extends('admin.layout')

@section('title', 'Dukungan')

@section('content')
    <div class="breadcrumb">Dukungan</div>
    <h1 class="page-title">Pusat dukungan &amp; operasional</h1>

    @php $metrics = $data['metrics'] ?? []; @endphp

    <div class="cards">
        <div class="card">
            <div class="k">Insiden terbuka</div>
            @if($metrics['available'] ?? false)
                <div class="v aish-num">{{ (int) ($metrics['open_support_incidents'] ?? 0) }}</div>
                <div class="sub">insiden dukungan tenant</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Tenant terdegradasi</div>
            @if($data['degraded_tenants']['available'] ?? false)
                <div class="v aish-num">{{ (int) ($data['degraded_tenants']['count'] ?? 0) }}</div>
                <div class="sub">butuh perhatian</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Anomali terbuka</div>
            @if($metrics['available'] ?? false)
                <div class="v aish-num">{{ (int) ($metrics['open_anomalies_total'] ?? 0) }}</div>
                <div class="sub">{{ (int) ($metrics['open_alert_suggestions'] ?? 0) }} saran alert</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Kesehatan aplikasi</div>
            @if($metrics['available'] ?? false)
                <div class="v" style="font-size:18px;">
                    @include('support.partials.status-badge', ['status' => $metrics['application_health'] ?? null])
                </div>
                <div class="sub">{{ implode(', ', $metrics['application_reason_codes'] ?? []) ?: '—' }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Insiden platform terbaru</h2>
        <div class="panel-body">
            @php $incidents = $data['recent_incidents']['items'] ?? []; @endphp
            @if(! ($data['recent_incidents']['available'] ?? false))
                <p class="unavailable">Tidak tersedia</p>
            @elseif(count($incidents) === 0)
                <div class="empty">Tidak ada insiden terbuka.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Referensi</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Area</th>
                                <th scope="col">Terdeteksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incidents as $row)
                                <tr>
                                    <td><a href="{{ route('admin.incidents.show', $row['id']) }}">{{ $row['reference'] }}</a></td>
                                    <td>@include('support.partials.status-badge', ['status' => $row['severity']])</td>
                                    <td>@include('support.partials.status-badge', ['status' => $row['status']])</td>
                                    <td>{{ $row['area'] ?? '—' }}</td>
                                    <td>{{ $row['detected_at'] ?? '—' }}</td>
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
                Konsol ini hanya-baca. Status berasal dari layanan kanonik Sprint 35/36 dan tidak dihitung ulang di sini.
                <a href="{{ route('admin.support.tenants') }}">Lihat kesehatan tenant →</a>
            </p>
        </div>
    </div>
@endsection
