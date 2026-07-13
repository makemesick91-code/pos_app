@extends('admin.layout')

@section('title', 'Penagihan tenant')

@section('content')
    <div class="breadcrumb">
        <a href="{{ route('admin.tenants.index') }}">Tenant</a> ·
        <a href="{{ route('admin.tenants.show', $tenant->id) }}">{{ $tenant->name }}</a> ·
        Penagihan
    </div>
    <h1 class="page-title">Penagihan · {{ $tenant->name }}</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Status langganan</div>
            <div class="v" style="font-size:18px;">
                @php $lc = $lifecycle['tenant_status'] ?? ($lifecycle['status'] ?? null); @endphp
                <span class="badge {{ in_array($lc, ['suspended','cancelled','archived'], true) ? 'badge-bad' : (in_array($lc, ['grace','past_due','onboarding'], true) ? 'badge-warn' : 'badge-ok') }}">
                    {{ $lc ?? 'Tidak tersedia' }}
                </span>
            </div>
        </div>
        <div class="card">
            <div class="k">Total faktur</div>
            @if($data['invoices']['available'] ?? false)
                <div class="v">{{ (int) ($data['invoices']['total_invoices'] ?? 0) }}</div>
                <div class="sub">Ditagih: <x-rupiah :amount="$data['invoices']['total_amount'] ?? null" /></div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
        <div class="card">
            <div class="k">Tertunggak</div>
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
            <div class="k">QRIS tersettle</div>
            @if($data['settlement']['available'] ?? false)
                <div class="v">{{ (int) ($data['settlement']['settled_intents'] ?? 0) }}</div>
                <div class="sub">{{ (int) ($data['settlement']['open_intents'] ?? 0) }} menunggu</div>
            @else
                <div class="v"><span class="unavailable">Tidak tersedia</span></div>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Faktur terbaru</h2>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Faktur terbaru untuk tenant ini</caption>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penagihan</th>
                        <th scope="col">Total</th>
                        <th scope="col">Tertunggak</th>
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
                                <td><x-rupiah :amount="$invoice['outstanding_amount'] ?? null" :currency="$invoice['currency'] ?? null" /></td>
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
    </div>
@endsection
