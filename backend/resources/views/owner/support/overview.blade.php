@extends('owner.layout')

@section('title', 'Dukungan')

@php $health = $data['health'] ?? []; @endphp

@section('content')
    <div class="breadcrumb">Dukungan</div>
    <h1 class="page-title">Dukungan &amp; status operasional</h1>

    @unless($data['operational'] ?? true)
        <div class="notice">
            Bisnis Anda sedang dalam status terbatas. Halaman ini tetap menampilkan status dan dukungan, namun sebagian data operasional dibatasi.
        </div>
    @endunless

    <div class="cards">
        <div class="card">
            <div class="k">Kesehatan bisnis Anda</div>
            @if($health['available'] ?? false)
                <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $health['health_status'] ?? null])</div>
                <div class="sub">{{ implode(', ', array_slice($health['reason_codes'] ?? [], 0, 3)) ?: '—' }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Penangguhan manual</div>
            <div class="v" style="font-size:18px;">{{ ($health['manual_suspension_active'] ?? false) ? 'Aktif' : 'Tidak' }}</div>
        </div>
        <div class="card">
            <div class="k">Sinkronisasi gagal</div>
            @if($data['sync_failures']['available'] ?? false)
                <div class="v aish-num">{{ (int) ($data['sync_failures']['failed_batch_count'] ?? 0) }}</div>
                <div class="sub">batch · {{ (int) ($data['sync_failures']['failed_item_count'] ?? 0) }} item</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Perangkat tersinkron</div>
            @if($data['sync']['available'] ?? false)
                <div class="v aish-num">{{ (int) ($data['sync']['device_count'] ?? 0) }}</div>
                <div class="sub">{{ (int) ($data['sync']['sync_batch_count'] ?? 0) }} batch</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Insiden dukungan Anda</h2>
        <div class="panel-body">
            @php $incidents = $data['incidents']['items'] ?? []; @endphp
            @if(! ($data['incidents']['available'] ?? false))
                <p class="unavailable">Tidak tersedia</p>
            @elseif(count($incidents) === 0)
                <div class="empty">Tidak ada insiden dukungan untuk bisnis Anda.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Nomor</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Judul</th>
                                <th scope="col"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incidents as $inc)
                                <tr>
                                    <td>{{ $inc['incident_number'] }}</td>
                                    <td>@include('support.partials.status-badge', ['status' => $inc['severity']])</td>
                                    <td>@include('support.partials.status-badge', ['status' => $inc['status']])</td>
                                    <td>{{ $inc['title'] ?? '—' }}</td>
                                    <td><a href="{{ route('owner.support.incidents.show', $inc['id']) }}">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Butuh bantuan?</h2>
        <div class="panel-body">
            <p style="font-size:14px;color:var(--aish-text-secondary);">
                Status ini hanya mencakup bisnis Anda. Detail infrastruktur platform, tenant lain, dan log internal tidak ditampilkan.
                Saat ini belum tersedia kanal pengajuan tiket mandiri dari halaman ini; hubungi tim dukungan Aish melalui saluran yang telah disediakan.
            </p>
        </div>
    </div>
@endsection
