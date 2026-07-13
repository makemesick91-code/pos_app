{{--
    UIX-6 — shared, accessible status/severity badge for the support,
    observability, and incident consoles. Always renders a TEXT label (never
    colour-only, UIX6-R011 truthful UI / rule 40 accessibility). An unknown,
    stale, or null status renders "Tidak tersedia" with a neutral tone — never a
    fabricated healthy state (UIX6-R013).

    @param string|null $status  Canonical status/severity value.
    @param string|null $label   Optional display label (defaults to the status).
--}}
@php
    $raw = $status !== null ? strtolower((string) $status) : null;
    $okSet = ['healthy', 'ok', 'active', 'resolved', 'closed', 'lunas', 'cleared'];
    $warnSet = ['watch', 'degraded', 'investigating', 'waiting_tenant', 'waiting_internal',
                'acknowledged', 'mitigated', 'accepted_risk', 'medium', 'p2', 'p3', 'high'];
    $badSet = ['blocked', 'critical', 'unhealthy', 'down', 'open', 'suspended', 'failed',
               'cancelled', 'p0', 'p1'];
    if ($raw === null || $raw === 'unknown' || $raw === 'stale' || $raw === '') {
        $tone = 'badge-neutral';
        $text = $label ?? 'Tidak tersedia';
    } elseif (in_array($raw, $okSet, true)) {
        $tone = 'badge-ok';
        $text = $label ?? ucfirst($raw);
    } elseif (in_array($raw, $warnSet, true)) {
        $tone = 'badge-warn';
        $text = $label ?? ucfirst($raw);
    } elseif (in_array($raw, $badSet, true)) {
        $tone = 'badge-bad';
        $text = $label ?? ucfirst($raw);
    } else {
        $tone = 'badge-neutral';
        $text = $label ?? ucfirst($raw);
    }
@endphp
<span class="badge {{ $tone }}">{{ $text }}</span>
