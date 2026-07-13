@extends('owner.layout')

@php $inv = $data['invoice']; @endphp

@section('title', 'Faktur '.($inv['invoice_number'] ?? ''))

@section('content')
    <div class="breadcrumb">
        <a href="{{ route('owner.billing') }}">Tagihan</a> ·
        <a href="{{ route('owner.billing.invoices') }}">Faktur</a> ·
        {{ $inv['invoice_number'] ?? '—' }}
    </div>
    <h1 class="page-title">Faktur {{ $inv['invoice_number'] ?? '—' }}</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Status faktur</div>
            <div class="v" style="font-size:18px;">@include('billing.partials.status-badge', ['type' => 'invoice', 'value' => $inv['status'] ?? null])</div>
        </div>
        <div class="card">
            <div class="k">Penagihan</div>
            <div class="v" style="font-size:18px;">@include('billing.partials.status-badge', ['type' => 'collection', 'value' => $inv['collection_state'] ?? null])</div>
        </div>
        <div class="card">
            <div class="k">Total</div>
            <div class="v" style="font-size:20px;"><x-rupiah :amount="$inv['total_amount'] ?? null" :currency="$inv['currency'] ?? null" /></div>
        </div>
        <div class="card">
            <div class="k">Tertunggak</div>
            <div class="v" style="font-size:20px;"><x-rupiah :amount="$inv['outstanding_amount'] ?? null" :currency="$inv['currency'] ?? null" /></div>
        </div>
    </div>

    <div class="panel">
        <h2>Rincian faktur</h2>
        <div class="panel-body">
            <dl class="kv">
                <dt>Nomor</dt><dd>{{ $inv['invoice_number'] ?? '—' }}</dd>
                <dt>Paket</dt><dd>{{ $inv['plan_key'] ?? '—' }}</dd>
                <dt>Periode</dt><dd>{{ $inv['period_key'] ?? '—' }}</dd>
                <dt>Rentang periode</dt>
                <dd>{{ optional($inv['period_start'] ?? null)->format('d M Y') ?? '—' }} – {{ optional($inv['period_end'] ?? null)->format('d M Y') ?? '—' }}</dd>
                <dt>Terbit</dt><dd>{{ optional($inv['issued_at'] ?? null)->format('d M Y H:i') ?? '—' }}</dd>
                <dt>Jatuh tempo</dt><dd>{{ optional($inv['due_at'] ?? null)->format('d M Y') ?? '—' }}</dd>
                <dt>Subtotal</dt><dd><x-rupiah :amount="$inv['subtotal_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd>
                <dt>Diskon</dt><dd><x-rupiah :amount="$inv['discount_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd>
                <dt>Pajak</dt><dd><x-rupiah :amount="$inv['tax_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd>
                <dt>Total</dt><dd><strong><x-rupiah :amount="$inv['total_amount'] ?? null" :currency="$inv['currency'] ?? null" /></strong></dd>
                <dt>Terkumpul</dt><dd><x-rupiah :amount="$inv['collected_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd>
                <dt>Tertunggak</dt><dd><x-rupiah :amount="$inv['outstanding_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd>
            </dl>
            <p style="margin-top:var(--aish-space-lg);">
                <a class="btn-ghost" href="{{ route('owner.billing.invoices.download', $inv['id']) }}">Unduh faktur</a>
            </p>
        </div>
    </div>

    <div class="panel">
        <h2>Pembayaran tercatat</h2>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Pembayaran tercatat untuk faktur ini</caption>
                <thead>
                    <tr>
                        <th scope="col">Referensi</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Status</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['payments'] as $p)
                        <tr>
                            <td>{{ $p['payment_reference'] ?? '—' }}</td>
                            <td>{{ $p['method'] ?? '—' }}</td>
                            <td>@include('billing.partials.status-badge', ['type' => 'payment', 'value' => $p['status'] ?? null])</td>
                            <td><x-rupiah :amount="$p['amount'] ?? null" :currency="$p['currency'] ?? null" /></td>
                            <td>{{ optional($p['received_at'] ?? null)->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Belum ada pembayaran tercatat.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Pembayaran QRIS &amp; settlement</h2>
        <div class="panel-body">
            <p class="sub" style="color:var(--aish-text-secondary);margin-top:0;">
                Status di bawah adalah status permintaan pembayaran di gateway. Faktur baru dianggap
                <strong>Lunas</strong> ketika penagihannya berstatus Lunas — bukan sekadar QRIS dibuat.
            </p>
        </div>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Permintaan pembayaran QRIS dan peristiwa settlement</caption>
                <thead>
                    <tr>
                        <th scope="col">Provider</th>
                        <th scope="col">Kanal</th>
                        <th scope="col">Status</th>
                        <th scope="col">Jumlah</th>
                        <th scope="col">Kedaluwarsa</th>
                        <th scope="col">Peristiwa terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['intents'] as $intent)
                        <tr>
                            <td>{{ $intent['provider'] ?? '—' }}</td>
                            <td>{{ $intent['channel'] ?? '—' }}</td>
                            <td>@include('billing.partials.status-badge', ['type' => 'intent', 'value' => $intent['status'] ?? null])</td>
                            <td><x-rupiah :amount="$intent['amount'] ?? null" :currency="$intent['currency'] ?? null" /></td>
                            <td>{{ optional($intent['expires_at'] ?? null)->format('d M Y H:i') ?? '—' }}</td>
                            <td>
                                @php $last = $intent['events'][0] ?? null; @endphp
                                @if($last)
                                    @include('billing.partials.status-badge', ['type' => 'event', 'value' => $last['normalized_status'] ?? null])
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">Belum ada permintaan pembayaran QRIS.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
