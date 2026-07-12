@extends('owner.layout')

@section('title', 'Perangkat')

@section('content')
    <div class="breadcrumb">Perangkat</div>
    <h1 class="page-title">Perangkat terdaftar</h1>

    <form method="GET" action="{{ route('owner.devices.index') }}" class="filters" role="search">
        <div>
            <label for="q">Cari</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Label perangkat">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua</option>
                <option value="activated" @selected($filters['status'] === 'activated')>Aktif</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Menunggu</option>
                <option value="revoked" @selected($filters['status'] === 'revoked')>Dicabut</option>
                <option value="expired" @selected($filters['status'] === 'expired')>Kedaluwarsa</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-ghost">Terapkan</button>
        </div>
    </form>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Label</th>
                        <th scope="col">Status</th>
                        <th scope="col">Diaktifkan</th>
                        <th scope="col">Terakhir terlihat</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        <tr>
                            <td>{{ $device->device_label ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $device->activation_status === 'activated' ? 'badge-ok' : ($device->activation_status === 'revoked' ? 'badge-bad' : 'badge-neutral') }}">
                                    {{ $device->activation_status }}
                                </span>
                            </td>
                            <td>{{ optional($device->activated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>{{ optional($device->last_seen_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td><a href="{{ route('owner.devices.show', $device->id) }}">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Belum ada perangkat yang cocok.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('owner.partials.pager', ['paginator' => $devices])
@endsection
