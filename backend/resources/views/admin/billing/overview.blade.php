@extends('admin.layout')

@section('title', 'Penagihan')

@section('content')
    <div class="breadcrumb">Penagihan platform</div>
    <h1 class="page-title">Operasi penagihan</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Total faktur</div>
            @if($data['invoices']['available'] ?? false)
                <div class="v">{{ (int) ($data['invoices']['total_invoices'] ?? 0) }}</div>
                <div class="sub">Total ditagih: <x-rupiah :amount="$data['invoices']['total_amount'] ?? null" /></div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Tertunggak (platform)</div>
            @if($data['collection']['available'] ?? false)
                <div class="v" style="font-size:22px;"><x-rupiah :amount="$data['collection']['total_outstanding_amount'] ?? null" /></div>
                <div class="sub">Terkumpul: <x-rupiah :amount="$data['collection']['total_collected_amount'] ?? null" /></div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Terlambat</div>
            @if($data['collection']['available'] ?? false)
                <div class="v">{{ (int) ($data['collection']['by_collection_state']['overdue'] ?? 0) }}</div>
                <div class="sub">Gagal: {{ (int) ($data['collection']['by_collection_state']['failed'] ?? 0) }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">QRIS tersettle</div>
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
        <div class="card">
            <div class="k">Intent pembayaran</div>
            @if($data['intents']['available'] ?? false)
                <div class="v">{{ (int) ($data['intents']['total'] ?? 0) }}</div>
                <div class="sub">Dibayar (gateway): {{ (int) ($data['intents']['by_status']['paid'] ?? 0) }}</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Faktur terbaru (semua tenant)</h2>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Faktur terbaru lintas tenant</caption>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Tenant</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penagihan</th>
                        <th scope="col">Total</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @if($data['recent']['available'] ?? false)
                        @forelse($data['recent']['items'] ?? [] as $invoice)
                            <tr>
                                <td>{{ $invoice['invoice_number'] ?? '—' }}</td>
                                <td><a href="{{ route('admin.tenants.billing', $invoice['tenant_id']) }}">#{{ $invoice['tenant_id'] }}</a></td>
                                <td>{{ $invoice['period_key'] ?? '—' }}</td>
                                <td>@include('billing.partials.status-badge', ['type' => 'invoice', 'value' => $invoice['status'] ?? null])</td>
                                <td>@include('billing.partials.status-badge', ['type' => 'collection', 'value' => $invoice['collection_state'] ?? null])</td>
                                <td><x-rupiah :amount="$invoice['total_amount'] ?? null" :currency="$invoice['currency'] ?? null" /></td>
                                <td><a class="btn-ghost" href="{{ route('admin.billing.invoices.show', $invoice['id']) }}">Detail</a></td>
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
            <a class="btn-ghost" href="{{ route('admin.billing.invoices') }}">Lihat semua faktur</a>
        </div>
    </div>
@endsection
