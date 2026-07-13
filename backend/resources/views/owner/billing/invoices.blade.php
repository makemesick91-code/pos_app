@extends('owner.layout')

@section('title', 'Faktur')

@section('content')
    <div class="breadcrumb"><a href="{{ route('owner.billing') }}">Tagihan</a> · Faktur</div>
    <h1 class="page-title">Daftar faktur</h1>

    <form method="GET" action="{{ route('owner.billing.invoices') }}" class="filters" role="search">
        <div>
            <label for="q">Cari nomor faktur</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="INV-…" autocomplete="off">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                @foreach($statusOptions as $opt)
                    <option value="{{ $opt }}" @selected(($filters['status'] ?? '') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="collection">Penagihan</label>
            <select id="collection" name="collection">
                <option value="">Semua</option>
                @foreach($collectionOptions as $opt)
                    <option value="{{ $opt }}" @selected(($filters['collection'] ?? '') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="period">Periode (YYYY-MM)</label>
            <input type="text" id="period" name="period" value="{{ $filters['period'] ?? '' }}" placeholder="2026-07" inputmode="numeric" pattern="\d{4}-\d{2}">
        </div>
        <div>
            <button type="submit" class="btn-ghost">Terapkan</button>
        </div>
    </form>

    <div class="panel">
        <h2>Faktur</h2>
        <div class="table-wrap">
            <table>
                <caption class="sr-only">Daftar faktur tenant Anda</caption>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Periode</th>
                        <th scope="col">Terbit</th>
                        <th scope="col">Jatuh tempo</th>
                        <th scope="col">Status</th>
                        <th scope="col">Penagihan</th>
                        <th scope="col">Total</th>
                        <th scope="col">Tertunggak</th>
                        <th scope="col"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $invoice)
                        <tr>
                            <td>{{ $invoice['invoice_number'] ?? '—' }}</td>
                            <td>{{ $invoice['period_key'] ?? '—' }}</td>
                            <td>{{ optional($invoice['issued_at'] ?? null)->format('d M Y') ?? '—' }}</td>
                            <td>{{ optional($invoice['due_at'] ?? null)->format('d M Y') ?? '—' }}</td>
                            <td>@include('billing.partials.status-badge', ['type' => 'invoice', 'value' => $invoice['status'] ?? null])</td>
                            <td>@include('billing.partials.status-badge', ['type' => 'collection', 'value' => $invoice['collection_state'] ?? null])</td>
                            <td><x-rupiah :amount="$invoice['total_amount'] ?? null" :currency="$invoice['currency'] ?? null" /></td>
                            <td><x-rupiah :amount="$invoice['outstanding_amount'] ?? null" :currency="$invoice['currency'] ?? null" /></td>
                            <td><a class="btn-ghost" href="{{ route('owner.billing.invoices.show', $invoice['id']) }}">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty">Belum ada faktur yang cocok.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('owner.partials.pager', ['paginator' => $paginator])
    </div>
@endsection
