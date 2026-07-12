@extends('owner.layout')

@section('title', 'Outlet')

@section('content')
    <div class="breadcrumb">Outlet</div>
    <h1 class="page-title">Outlet bisnis</h1>

    <form method="GET" action="{{ route('owner.outlets.index') }}" class="filters" role="search">
        <div>
            <label for="q">Cari</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Nama atau kode outlet">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="all" @selected($filters['status'] === 'all')>Semua</option>
                <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div>
            <label for="sort">Urutkan</label>
            <select id="sort" name="sort">
                <option value="name" @selected($filters['sort'] === 'name')>Nama</option>
                <option value="code" @selected($filters['sort'] === 'code')>Kode</option>
                <option value="updated_at" @selected($filters['sort'] === 'updated_at')>Terakhir diperbarui</option>
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
                        <th scope="col">Nama</th>
                        <th scope="col">Kode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Diperbarui</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($outlets as $outlet)
                        <tr>
                            <td>{{ $outlet->name }}</td>
                            <td>{{ $outlet->code }}</td>
                            <td>
                                <span class="badge {{ $outlet->is_active ? 'badge-ok' : 'badge-neutral' }}">
                                    {{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>{{ optional($outlet->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td><a href="{{ route('owner.outlets.show', $outlet->id) }}">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Belum ada outlet yang cocok.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('owner.partials.pager', ['paginator' => $outlets])
@endsection
