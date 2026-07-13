@extends('admin.layout')

@section('title', 'Detail dukungan tenant')

@php $tenant = $data['tenant']; $health = $data['health'] ?? []; @endphp

@section('content')
    <div class="breadcrumb">
        <a href="{{ route('admin.support') }}">Dukungan</a> ·
        <a href="{{ route('admin.support.tenants') }}">Tenant</a> · {{ $tenant->name }}
    </div>
    <h1 class="page-title">{{ $tenant->name }} <span style="font-size:14px;color:var(--aish-text-secondary);">({{ $tenant->code }})</span></h1>

    <div class="cards">
        <div class="card">
            <div class="k">Status tenant</div>
            <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $tenant->status])</div>
        </div>
        <div class="card">
            <div class="k">Kesehatan</div>
            @if($health['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $health['health_status'] ?? null])</div>
                <div class="sub">{{ implode(', ', array_slice($health['reason_codes'] ?? [], 0, 4)) ?: '—' }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Penangguhan manual</div>
            <div class="v" style="font-size:18px;">
                {{ ($health['manual_suspension_active'] ?? false) ? 'Aktif' : 'Tidak' }}
            </div>
        </div>
        <div class="card">
            <div class="k">Perangkat</div>
            @if($data['sync']['available'] ?? false)
                <div class="v aish-num">{{ (int) ($data['sync']['device_count'] ?? 0) }}</div>
                <div class="sub">{{ (int) ($data['sync']['revoked_device_count'] ?? 0) }} dicabut</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Sinkronisasi &amp; onboarding</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Batch sinkron gagal</dt>
                <dd>@if($data['sync_failures']['available'] ?? false){{ (int) ($data['sync_failures']['failed_batch_count'] ?? 0) }}@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
                <dt>Item gagal</dt>
                <dd>@if($data['sync_failures']['available'] ?? false){{ (int) ($data['sync_failures']['failed_item_count'] ?? 0) }}@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
                <dt>Proses onboarding</dt>
                <dd>@if($data['onboarding']['available'] ?? false){{ (int) ($data['onboarding']['run_count'] ?? 0) }}@else<span class="unavailable">Tidak tersedia</span>@endif</dd>
            </dl>
            <p style="margin-top:var(--aish-space-md);font-size:13px;">
                <a href="{{ route('admin.tenants.show', $tenant->id) }}">Profil tenant</a> ·
                <a href="{{ route('admin.tenants.billing', $tenant->id) }}">Penagihan tenant</a>
            </p>
        </div>
    </div>

    <div class="panel">
        <h2>Insiden dukungan tenant</h2>
        <div class="panel-body">
            @php $incidents = $data['incidents']['items'] ?? []; @endphp
            @if(! ($data['incidents']['available'] ?? false))
                <p class="unavailable">Tidak tersedia</p>
            @elseif(count($incidents) === 0)
                <div class="empty">Tidak ada insiden dukungan untuk tenant ini.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Nomor</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Judul</th>
                                <th scope="col">Dibuka</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incidents as $inc)
                                <tr>
                                    <td>{{ $inc['incident_number'] }}</td>
                                    <td>@include('support.partials.status-badge', ['status' => $inc['severity']])</td>
                                    <td>@include('support.partials.status-badge', ['status' => $inc['status']])</td>
                                    <td>{{ $inc['title'] ?? '—' }}</td>
                                    <td>{{ $inc['opened_at'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Lini masa diagnostik (teredaksi)</h2>
        <div class="panel-body">
            @php $events = $data['timeline']['events'] ?? []; @endphp
            @if(! ($data['timeline']['available'] ?? false))
                <p class="unavailable">Tidak tersedia</p>
            @elseif(count($events) === 0)
                <div class="empty">Tidak ada peristiwa.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Waktu</th>
                                <th scope="col">Sumber</th>
                                <th scope="col">Kategori</th>
                                <th scope="col">Ringkasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($events as $ev)
                                <tr>
                                    <td>{{ $ev['at'] ?? '—' }}</td>
                                    <td>{{ $ev['source'] ?? '—' }}</td>
                                    <td>{{ $ev['category'] ?? '—' }}</td>
                                    <td>{{ $ev['summary'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
