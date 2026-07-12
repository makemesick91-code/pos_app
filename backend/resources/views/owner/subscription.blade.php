@extends('owner.layout')

@section('title', 'Langganan')

@section('content')
    <div class="breadcrumb">Langganan</div>
    <h1 class="page-title">Langganan &amp; tagihan</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Status lifecycle</div>
            <div class="v" style="font-size:20px;">
                @include('owner.partials.lifecycle-badge', ['lifecycle' => $data['lifecycle']])
            </div>
        </div>
        <div class="card">
            <div class="k">Paket</div>
            @if($data['plan']['available'] ?? false)
                <div class="v" style="font-size:20px;">{{ $data['plan']['plan_name'] }}</div>
                <div class="sub">{{ $data['plan']['has_explicit_assignment'] ? 'Penetapan aktif' : 'Paket bawaan' }}</div>
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

    <div class="panel">
        <h2>Faktur terbaru</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penagihan</th>
                        <th scope="col">Total</th>
                        <th scope="col">Jatuh tempo</th>
                    </tr>
                </thead>
                <tbody>
                    @if($data['billing']['available'] ?? false)
                        @forelse($data['billing']['latest'] ?? [] as $invoice)
                            <tr>
                                <td>{{ $invoice['invoice_number'] ?? '—' }}</td>
                                <td>{{ $invoice['period_key'] ?? '—' }}</td>
                                <td>{{ $invoice['status'] ?? '—' }}</td>
                                <td>{{ $invoice['collection_state'] ?? '—' }}</td>
                                <td class="aish-num">{{ number_format((int) ($invoice['total_amount'] ?? 0), 0, ',', '.') }}</td>
                                <td>{{ $invoice['due_at'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty">Belum ada faktur.</div></td></tr>
                        @endforelse
                    @else
                        <tr><td colspan="6"><div class="empty"><span class="unavailable">Tidak tersedia</span></div></td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
