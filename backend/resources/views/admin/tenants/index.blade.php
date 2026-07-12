@extends('admin.layout')

@section('title', 'Tenant')

@php
    $badge = function (?string $status): string {
        $s = strtoupper((string) $status);
        return match (true) {
            $s === 'ACTIVE' => 'success',
            in_array($s, ['GRACE', 'PAST_DUE', 'ONBOARDING'], true) => 'warning',
            in_array($s, ['SUSPENDED', 'CANCELLED', 'ARCHIVED', 'INACTIVE'], true) => 'danger',
            default => 'info',
        };
    };
    $paginator->appends(request()->only(['q', 'status', 'per_page']));
@endphp

@section('content')
    <div class="breadcrumb"><a href="{{ route('admin.dashboard') }}">Control Center</a> · Tenant</div>
    <h1 class="page-title">Manajemen Tenant</h1>

    <form method="GET" action="{{ route('admin.tenants.index') }}" class="filters" role="search">
        <div>
            <label for="q">Cari</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Nama, kode, atau pemilik">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                @foreach ($statusOptions as $opt)
                    <option value="{{ $opt }}" @selected($filters['status'] === $opt)>{{ ucfirst($opt) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="per_page">Per halaman</label>
            <select id="per_page" name="per_page">
                @foreach ([20, 30, 50] as $n)
                    <option value="{{ $n }}" @selected((int) $filters['per_page'] === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="aish-btn-primary" style="width:auto;padding:0 20px;height:42px;">Terapkan</button>
        </div>
    </form>

    <div class="panel" style="margin-top:0;">
        <div class="table-wrap">
            <table>
                <caption class="sr-only" style="position:absolute;left:-9999px;">Daftar tenant</caption>
                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col">Status</th>
                        <th scope="col">Paket</th>
                        <th scope="col">Outlet</th>
                        <th scope="col">Perangkat</th>
                        <th scope="col">Diperbarui</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <th scope="row" style="font-weight:600;">
                                <a href="{{ route('admin.tenants.show', $row['id']) }}">{{ $row['name'] }}</a>
                                <div class="sub">{{ $row['code'] }}@if($row['owner_name']) · {{ $row['owner_name'] }}@endif</div>
                            </th>
                            <td>
                                <span class="aish-badge aish-badge--{{ $badge($row['lifecycle_status']) }}">{{ strtoupper($row['lifecycle_status']) }}</span>
                                @if ($row['manually_suspended'])
                                    <span class="aish-badge aish-badge--danger">MANUAL</span>
                                @endif
                            </td>
                            <td>{{ $row['subscription']['available'] ? ($row['subscription']['plan_name'] ?? '—') : '—' }}</td>
                            <td class="aish-num">{{ $row['stores_count'] }}</td>
                            <td class="aish-num">{{ $row['devices_active_count'] }}</td>
                            <td class="sub">{{ optional($row['updated_at'])->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Tidak ada tenant yang cocok dengan filter.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($paginator->hasPages())
        <nav class="pager" aria-label="Navigasi halaman">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif
            <span class="current" aria-current="page">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
@endsection
