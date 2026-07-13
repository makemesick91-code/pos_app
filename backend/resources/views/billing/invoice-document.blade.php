<!DOCTYPE html>
@php $inv = $invoice; @endphp
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>Faktur {{ $inv['invoice_number'] ?? '' }} · Aish POS</title>
    <style>{!! file_get_contents(resource_path('css/aish-tokens.css')) !!}</style>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: var(--aish-font); color: var(--aish-text-primary);
            background: var(--aish-bg-default); margin: 0; padding: var(--aish-space-2xl);
        }
        .doc { max-width: 820px; margin: 0 auto; background: var(--aish-surface);
            border: 1px solid var(--aish-border); border-radius: var(--aish-radius-card); padding: var(--aish-space-2xl); }
        .doc-head { display: flex; justify-content: space-between; flex-wrap: wrap; gap: var(--aish-space-lg);
            border-bottom: 1px solid var(--aish-border); padding-bottom: var(--aish-space-lg); margin-bottom: var(--aish-space-lg); }
        .brand-title { font-weight: 800; font-size: 18px; color: var(--aish-brand-dark); }
        .muted { color: var(--aish-text-secondary); font-size: 13px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: var(--aish-space-md); }
        th, td { text-align: left; padding: 10px var(--aish-space-md); border-bottom: 1px solid var(--aish-border-subtle); }
        th { color: var(--aish-text-secondary); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        td.amount, th.amount { text-align: right; }
        .totals { margin-top: var(--aish-space-lg); margin-left: auto; width: min(340px, 100%); }
        .totals dt { color: var(--aish-text-secondary); }
        .totals .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--aish-border-subtle); }
        .totals .grand { font-weight: 800; font-size: 16px; border-bottom: none; }
        .unavailable { color: var(--aish-text-disabled); font-weight: 700; }
        .aish-num { font-variant-numeric: tabular-nums; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid var(--aish-border); }
        .badge-ok { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .badge-warn { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .badge-bad { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .badge-neutral { background: var(--aish-bg-subtle); color: var(--aish-text-secondary); }
        .actions { margin-bottom: var(--aish-space-lg); }
        .btn { border: 1px solid var(--aish-border); background: #fff; border-radius: var(--aish-radius-input);
            padding: 8px 14px; font: inherit; font-weight: 600; cursor: pointer; }
        @media print { body { padding: 0; } .doc { border: none; } .actions { display: none; } }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" class="btn" onclick="window.print()">Cetak faktur</button></div>
    <div class="doc">
        <div class="doc-head">
            <div>
                <div class="brand-title">Aish POS</div>
                <div class="muted">Faktur langganan SaaS</div>
            </div>
            <div style="text-align:right;">
                <h1>{{ $inv['invoice_number'] ?? '—' }}</h1>
                <div class="muted">Periode {{ $inv['period_key'] ?? '—' }}</div>
                <div>@include('billing.partials.status-badge', ['type' => 'collection', 'value' => $inv['collection_state'] ?? null])</div>
            </div>
        </div>

        <table>
            <tbody>
                <tr>
                    <th scope="row">Ditagihkan kepada</th>
                    <td>{{ $tenant['name'] ?? '—' }} <span class="muted">({{ $tenant['code'] ?? '—' }})</span></td>
                </tr>
                <tr><th scope="row">Paket</th><td>{{ $inv['plan_key'] ?? '—' }}</td></tr>
                <tr><th scope="row">Terbit</th><td>{{ optional($inv['issued_at'] ?? null)->format('d M Y') ?? '—' }}</td></tr>
                <tr><th scope="row">Jatuh tempo</th><td>{{ optional($inv['due_at'] ?? null)->format('d M Y') ?? '—' }}</td></tr>
                <tr><th scope="row">Status faktur</th><td>@include('billing.partials.status-badge', ['type' => 'invoice', 'value' => $inv['status'] ?? null])</td></tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><dt>Subtotal</dt><dd><x-rupiah :amount="$inv['subtotal_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
            <div class="row"><dt>Diskon</dt><dd><x-rupiah :amount="$inv['discount_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
            <div class="row"><dt>Pajak</dt><dd><x-rupiah :amount="$inv['tax_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
            <div class="row grand"><dt>Total</dt><dd><x-rupiah :amount="$inv['total_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
            <div class="row"><dt>Terkumpul</dt><dd><x-rupiah :amount="$inv['collected_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
            <div class="row"><dt>Tertunggak</dt><dd><x-rupiah :amount="$inv['outstanding_amount'] ?? null" :currency="$inv['currency'] ?? null" /></dd></div>
        </div>

        @if(count($payments) > 0)
            <h2 style="font-size:15px;margin-top:var(--aish-space-2xl);">Pembayaran tercatat</h2>
            <table>
                <thead>
                    <tr>
                        <th scope="col">Referensi</th>
                        <th scope="col">Metode</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="amount">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr>
                            <td>{{ $p['payment_reference'] ?? '—' }}</td>
                            <td>{{ $p['method'] ?? '—' }}</td>
                            <td>@include('billing.partials.status-badge', ['type' => 'payment', 'value' => $p['status'] ?? null])</td>
                            <td class="amount"><x-rupiah :amount="$p['amount'] ?? null" :currency="$p['currency'] ?? null" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="muted" style="margin-top:var(--aish-space-2xl);">
            Dokumen ini dihasilkan dari data resmi Aish POS. Nilai bersifat final sesuai catatan sistem penagihan.
        </p>
    </div>
</body>
</html>
