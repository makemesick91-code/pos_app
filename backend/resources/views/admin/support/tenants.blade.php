@extends('admin.layout')

@section('title', 'Kesehatan tenant')

@section('content')
    <div class="breadcrumb"><a href="{{ route('admin.support') }}">Dukungan</a> · Tenant</div>
    <h1 class="page-title">Kesehatan tenant</h1>

    <form method="GET" action="{{ route('admin.support.tenants') }}" class="filters">
        <div>
            <label for="q">Cari</label>
            <input type="text" id="q" name="q" value="{{ $filters['search'] ?? '' }}" placeholder="Nama atau kode">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Ditangguhkan</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div><button type="submit" class="btn-ghost">Terapkan</button></div>
    </form>

    <div class="panel">
        <div class="panel-body">
            @if(count($rows) === 0)
                <div class="empty">Tidak ada tenant yang cocok.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Tenant</th>
                                <th scope="col">Kode</th>
                                <th scope="col">Status tenant</th>
                                <th scope="col">Kesehatan</th>
                                <th scope="col">Catatan</th>
                                <th scope="col"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php $brief = $row['brief']; $tenant = $row['tenant']; @endphp
                                <tr>
                                    <td>{{ $tenant->name }}</td>
                                    <td>{{ $tenant->code }}</td>
                                    <td>@include('support.partials.status-badge', ['status' => $tenant->status])</td>
                                    <td>
                                        @if($brief['available'] ?? false)
                                            @include('support.partials.status-badge', ['status' => $brief['health_status'] ?? null])
                                        @else
                                            <span class="unavailable">Tidak tersedia</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($brief['available'] ?? false)
                                            {{ implode(', ', array_slice($brief['reason_codes'] ?? [], 0, 3)) ?: '—' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><a href="{{ route('admin.support.tenants.show', $tenant->id) }}">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $paginator->links('billing.partials.pager') }}
            @endif
        </div>
    </div>
@endsection
