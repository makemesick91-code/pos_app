@extends('owner.layout')

@section('title', 'Tagihan')

@section('content')
    <div class="breadcrumb">Tagihan</div>
    <h1 class="page-title">Pusat tagihan</h1>

    @if(! ($context->operational()))
        <div class="notice" role="status">
            Bisnis Anda sedang dibatasi. Anda tetap dapat melihat langganan dan tagihan agar dapat
            menyelesaikan pembayaran, namun operasional kasir dibatasi sampai status pulih.
        </div>
    @endif

    <div class="cards">
        <div class="card">
            <div class="k">Status langganan</div>
            <div class="v" style="font-size:20px;">
                @include('owner.partials.lifecycle-badge', ['lifecycle' => $data['lifecycle']])
            </div>
        </div>
        <div class="card">
            <div class="k">Paket</div>
            @if($data['plan']['available'] ?? false)
                <div class="v" style="font-size:20px;">{{ $data['plan']['plan_name'] }}</div>
                <div class="sub">{{ ($data['plan']['has_explicit_assignment'] ?? false) ? 'Penetapan aktif' : 'Paket bawaan' }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Tagihan tertunggak</div>
            <div class="v" style="font-size:20px;">
                @if($data['collection']['available'] ?? false)
                    <x-rupiah :amount="$data['collection']['total_outstanding_amount'] ?? null" />
                @else
                    <span class="unavailable">Tidak tersedia</span>
                @endif
            </div>
            @if($data['collection']['available'] ?? false)
                <div class="sub">Terkumpul: <x-rupiah :amount="$data['collection']['total_collected_amount'] ?? null" /></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Faktur</div>
            @if($data['invoices']['available'] ?? false)
                <div class="v">{{ (int) ($data['invoices']['total_invoices'] ?? 0) }}</div>
                <div class="sub">Total ditagih: <x-rupiah :amount="$data['invoices']['total_amount'] ?? null" /></div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Pembayaran QRIS tersettle</div>
            @if($data['settlement']['available'] ?? false)
                <div class="v">{{ (int) ($data['settlement']['settled_intents'] ?? 0) }}</div>
                <div class="sub">
                    Nilai: <x-rupiah :amount="$data['settlement']['settled_amount'] ?? null" />
                    · {{ (int) ($data['settlement']['open_intents'] ?? 0) }} menunggu
                </div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Faktur terbaru</h2>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Faktur terbaru untuk bisnis Anda</caption>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penagihan</th>
                        <th scope="col">Total</th>
                        <th scope="col">Jatuh tempo</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @if($data['recent']['available'] ?? false)
                        @forelse($data['recent']['items'] ?? [] as $invoice)
                            <tr>
                                <td>{{ $invoice['invoice_number'] ?? '—' }}</td>
                                <td>{{ $invoice['period_key'] ?? '—' }}</td>
                                <td>@include('billing.partials.status-badge', ['type' => 'invoice', 'value' => $invoice['status'] ?? null])</td>
                                <td>@include('billing.partials.status-badge', ['type' => 'collection', 'value' => $invoice['collection_state'] ?? null])</td>
                                <td><x-rupiah :amount="$invoice['total_amount'] ?? null" :currency="$invoice['currency'] ?? null" /></td>
                                <td>{{ optional($invoice['due_at'] ?? null)->format('d M Y') ?? '—' }}</td>
                                <td><a class="btn-ghost" href="{{ route('owner.billing.invoices.show', $invoice['id']) }}">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty">Belum ada faktur.</div></td></tr>
                        @endforelse
                    @else
                        <tr><td colspan="7"><div class="empty"><span class="unavailable">Tidak tersedia</span></div></td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="panel-body">
            <a class="btn-ghost" href="{{ route('owner.billing.invoices') }}">Lihat semua faktur</a>
        </div>
    </div>
@endsection
