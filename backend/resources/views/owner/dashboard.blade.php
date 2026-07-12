@extends('owner.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="breadcrumb">Dashboard</div>
    <h1 class="page-title">Ringkasan bisnis</h1>

    @unless($data['operational'])
        <div class="notice bad" role="status">
            <strong>Akses operasional bisnis Anda sedang dibatasi.</strong>
            <p style="margin:.5rem 0 0;">
                Status:
                @include('owner.partials.lifecycle-badge', ['lifecycle' => $data['lifecycle']])
                @if($data['lifecycle']->reason) — {{ $data['lifecycle']->reason }} @endif
            </p>
            <p style="margin:.5rem 0 0;"><a href="{{ route('owner.subscription') }}">Tinjau langganan &amp; tagihan</a></p>
        </div>
    @endunless

    <div class="cards">
        <div class="card">
            <div class="k">Status langganan</div>
            <div class="v" style="font-size:20px;">
                @include('owner.partials.lifecycle-badge', ['lifecycle' => $data['lifecycle']])
            </div>
            <div class="sub">Sumber otoritatif lifecycle tenant</div>
        </div>

        @if($data['operational'])
        <div class="card">
            <div class="k">Outlet aktif</div>
            @if($data['outlets']['available'] ?? false)
                <div class="v">{{ $data['outlets']['active'] }}</div>
                <div class="sub">dari {{ $data['outlets']['total'] }} outlet</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>

        <div class="card">
            <div class="k">Perangkat</div>
            @if($data['devices']['available'] ?? false)
                <div class="v">{{ $data['devices']['activated'] }}</div>
                <div class="sub">aktif · {{ $data['devices']['total'] }} terdaftar · {{ $data['devices']['revoked'] }} dicabut</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        @endif

        <div class="card">
            <div class="k">Paket langganan</div>
            @if($data['plan']['available'] ?? false)
                <div class="v" style="font-size:20px;">{{ $data['plan']['plan_name'] }}</div>
                <div class="sub">{{ $data['plan']['has_explicit_assignment'] ? 'Penetapan aktif' : 'Paket bawaan' }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>

        <div class="card">
            <div class="k">Kesehatan operasional</div>
            @if($data['health']['available'] ?? false)
                <div class="v" style="font-size:18px;">{{ ucfirst($data['health']['health_status'] ?? '—') }}</div>
                <div class="sub">{{ implode(', ', $data['health']['reason_codes'] ?? []) }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>

        <div class="card">
            <div class="k">Tagihan tertunggak</div>
            @if($data['billing']['available'] ?? false)
                <div class="v" style="font-size:20px;">{{ number_format((int) ($data['billing']['outstanding_amount'] ?? 0), 0, ',', '.') }}</div>
                <div class="sub">{{ $data['billing']['currency'] ?? '' }} · {{ (int) ($data['billing']['invoice_count'] ?? 0) }} faktur</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    @if($data['operational'])
    <div class="panel">
        <h2>Ringkasan penjualan hari ini</h2>
        <div class="panel-body">
            @if($data['sales_today']['available'] ?? false)
                <dl class="kv">
                    <dt>Tanggal</dt><dd>{{ $data['sales_today']['business_date'] ?? '—' }}</dd>
                    <dt>Jumlah transaksi</dt><dd>{{ (int) ($data['sales_today']['sales_count'] ?? 0) }}</dd>
                    <dt>Total penjualan (dibayar)</dt><dd class="aish-num">{{ $data['sales_today']['grand_total'] ?? '0.00' }}</dd>
                    <dt>Transaksi tunai</dt><dd>{{ (int) ($data['sales_today']['cash_sales_count'] ?? 0) }}</dd>
                    <dt>Transaksi QRIS</dt><dd>{{ (int) ($data['sales_today']['qris_sales_count'] ?? 0) }}</dd>
                    <dt>Transaksi dibatalkan</dt><dd>{{ (int) ($data['sales_today']['cancelled_sales_count'] ?? 0) }}</dd>
                </dl>
            @else
                <p class="unavailable">Tidak tersedia</p>
            @endif
        </div>
    </div>
    @endif
@endsection
