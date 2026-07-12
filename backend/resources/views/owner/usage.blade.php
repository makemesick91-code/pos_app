@extends('owner.layout')

@section('title', 'Penggunaan')

@php
    $usageLabels = [
        'branch' => 'Outlet / cabang',
        'outlet' => 'Outlet',
        'register' => 'Kasir register',
        'user' => 'Pengguna',
        'cashier' => 'Kasir',
        'device' => 'Perangkat',
    ];
@endphp

@section('content')
    <div class="breadcrumb">Penggunaan</div>
    <h1 class="page-title">Entitlement &amp; penggunaan</h1>

    <div class="panel">
        <h2>Paket</h2>
        <div class="panel-body">
            @if($data['plan']['available'] ?? false)
                <dl class="kv">
                    <dt>Paket</dt><dd>{{ $data['plan']['plan_name'] }}</dd>
                    <dt>Sumber</dt><dd>{{ $data['plan']['has_explicit_assignment'] ? 'Penetapan aktif' : 'Paket bawaan' }}</dd>
                </dl>
            @else
                <p class="unavailable">Tidak tersedia</p>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Penggunaan terhadap batas</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Sumber daya</th>
                        <th scope="col">Terpakai</th>
                        <th scope="col">Batas</th>
                    </tr>
                </thead>
                <tbody>
                    @if($data['usage']['available'] ?? false)
                        @foreach(collect($data['usage'])->except('available') as $alias => $row)
                            <tr>
                                <td>{{ $usageLabels[$alias] ?? ucfirst($alias) }}</td>
                                <td>{{ (int) ($row['current'] ?? 0) }}</td>
                                <td>
                                    @if($row['unlimited'] ?? false)
                                        Tanpa batas
                                    @elseif(($row['limit'] ?? null) !== null)
                                        {{ (int) $row['limit'] }}
                                    @else
                                        <span class="unavailable">Tidak tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr><td colspan="3"><div class="empty"><span class="unavailable">Tidak tersedia</span></div></td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
