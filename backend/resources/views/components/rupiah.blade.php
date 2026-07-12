@props(['amount' => null, 'currency' => null])
{{-- UIX-5 — canonical money display. Amounts are whole-rupiah integers from the
     domain (never floats, never divided by 100 — UIX5-R008). A null amount is a
     truthful "Tidak tersedia", never a fabricated zero (UIX5-R013). This is the
     ONLY place billing amounts are formatted for the console, so no view performs
     financial arithmetic (UIX5-R002/R010). --}}
@php $c = $currency ?: 'IDR'; @endphp
@if($amount === null || $amount === '')<span class="unavailable">Tidak tersedia</span>@else<span class="aish-num">{{ $c === 'IDR' ? 'Rp ' : $c.' ' }}{{ number_format((int) $amount, 0, ',', '.') }}</span>@endif
