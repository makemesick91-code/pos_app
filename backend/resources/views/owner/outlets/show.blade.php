@extends('owner.layout')

@section('title', 'Detail Outlet')

@php $outlet = $detail['outlet']; @endphp

@section('content')
    <div class="breadcrumb"><a href="{{ route('owner.outlets.index') }}">Outlet</a> · {{ $outlet->name }}</div>
    <h1 class="page-title">{{ $outlet->name }}</h1>

    <div class="panel">
        <h2>Identitas outlet</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Nama</dt><dd>{{ $outlet->name }}</dd>
                <dt>Kode</dt><dd>{{ $outlet->code }}</dd>
                <dt>Status</dt>
                <dd><span class="badge {{ $outlet->is_active ? 'badge-ok' : 'badge-neutral' }}">{{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}</span></dd>
                <dt>Alamat</dt><dd>{{ $outlet->address ?: '—' }}</dd>
                <dt>Telepon</dt><dd>{{ $outlet->phone ?: '—' }}</dd>
                <dt>Pengguna</dt>
                <dd>
                    @if($detail['user_count']['available'] ?? false)
                        {{ $detail['user_count']['value'] }} ({{ $detail['active_user_count']['value'] ?? 0 }} aktif)
                    @else
                        <span class="unavailable">Tidak tersedia</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <h2>Perangkat pada outlet ini</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Label</th>
                        <th scope="col">Status</th>
                        <th scope="col">Terakhir terlihat</th>
                    </tr>
                </thead>
                <tbody>
                    @if($detail['devices']['available'] ?? false)
                        @forelse(collect($detail['devices'])->except('available') as $device)
                            <tr>
                                <td>{{ $device['device_label'] ?? '—' }}</td>
                                <td>{{ $device['status'] ?? '—' }}</td>
                                <td>{{ $device['last_seen_at'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="empty">Belum ada perangkat pada outlet ini.</div></td></tr>
                        @endforelse
                    @else
                        <tr><td colspan="3"><div class="empty"><span class="unavailable">Tidak tersedia</span></div></td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
