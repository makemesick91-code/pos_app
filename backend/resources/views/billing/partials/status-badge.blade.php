@php
    /**
     * UIX-5 — labelled status badge. The text label (not colour alone) always
     * communicates state, for accessibility (rule 40). Lifecycle states are kept
     * SEMANTICALLY DISTINCT (UIX5-R011): an intent that is "Dibayar" at the
     * gateway is NOT the same as an invoice whose collection is "Lunas".
     *
     * @var string $type   one of invoice|collection|payment|intent|event
     * @var string|null $value  the raw canonical status value
     */
    $labels = [
        'invoice' => [
            'draft' => 'Draf', 'issued' => 'Terbit', 'void' => 'Batal (void)', 'cancelled' => 'Dibatalkan',
        ],
        'collection' => [
            'not_due' => 'Belum jatuh tempo', 'pending' => 'Menunggu pembayaran', 'paid' => 'Lunas',
            'failed' => 'Gagal', 'overdue' => 'Terlambat', 'written_off' => 'Dihapusbukukan', 'cancelled' => 'Dibatalkan',
        ],
        'payment' => [
            'pending' => 'Menunggu', 'recorded' => 'Tercatat', 'confirmed' => 'Terkonfirmasi',
            'failed' => 'Gagal', 'cancelled' => 'Dibatalkan', 'refunded' => 'Dikembalikan',
        ],
        'intent' => [
            'pending' => 'Menunggu', 'requires_action' => 'Perlu tindakan', 'paid' => 'Dibayar (gateway)',
            'expired' => 'Kedaluwarsa', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan',
        ],
        'event' => [
            'received' => 'Diterima', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak',
            'processed' => 'Diproses', 'ignored' => 'Diabaikan', 'replayed' => 'Diulang',
            'paid' => 'Dibayar', 'failed' => 'Gagal', 'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan', 'requires_action' => 'Perlu tindakan',
        ],
    ];
    $classes = [
        'invoice' => ['draft' => 'badge-neutral', 'issued' => 'badge-neutral', 'void' => 'badge-bad', 'cancelled' => 'badge-bad'],
        'collection' => [
            'not_due' => 'badge-neutral', 'pending' => 'badge-warn', 'paid' => 'badge-ok',
            'failed' => 'badge-bad', 'overdue' => 'badge-bad', 'written_off' => 'badge-neutral', 'cancelled' => 'badge-neutral',
        ],
        'payment' => [
            'pending' => 'badge-warn', 'recorded' => 'badge-ok', 'confirmed' => 'badge-ok',
            'failed' => 'badge-bad', 'cancelled' => 'badge-neutral', 'refunded' => 'badge-neutral',
        ],
        'intent' => [
            'pending' => 'badge-warn', 'requires_action' => 'badge-warn', 'paid' => 'badge-ok',
            'expired' => 'badge-neutral', 'failed' => 'badge-bad', 'cancelled' => 'badge-neutral',
        ],
        'event' => [
            'received' => 'badge-neutral', 'verified' => 'badge-ok', 'rejected' => 'badge-bad',
            'processed' => 'badge-ok', 'ignored' => 'badge-neutral', 'replayed' => 'badge-warn',
            'paid' => 'badge-ok', 'failed' => 'badge-bad', 'expired' => 'badge-neutral',
            'cancelled' => 'badge-neutral', 'requires_action' => 'badge-warn',
        ],
    ];

    $v = (string) ($value ?? '');
    if ($v === '') {
        $label = 'Tidak tersedia';
        $class = 'badge-neutral';
    } else {
        $label = $labels[$type][$v] ?? ucfirst(str_replace('_', ' ', $v));
        $class = $classes[$type][$v] ?? 'badge-neutral';
    }
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
