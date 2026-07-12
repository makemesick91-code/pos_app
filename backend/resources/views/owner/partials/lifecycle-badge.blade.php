@php
    /** @var \App\Services\TenantLifecycle\TenantLifecycleDecision $lifecycle */
    $statusLabels = [
        'onboarding' => 'Onboarding',
        'active' => 'Aktif',
        'grace' => 'Masa Tenggang',
        'past_due' => 'Menunggak',
        'suspended' => 'Ditangguhkan',
        'cancelled' => 'Dibatalkan',
        'archived' => 'Diarsipkan',
    ];
    $badgeClass = match ($lifecycle->status) {
        'active' => 'badge-ok',
        'grace', 'past_due', 'onboarding' => 'badge-warn',
        'suspended', 'cancelled', 'archived' => 'badge-bad',
        default => 'badge-neutral',
    };
@endphp
<span class="badge {{ $badgeClass }}">{{ $statusLabels[$lifecycle->status] ?? $lifecycle->status }}</span>
