@extends('public-website.layout')

{{-- Sprint 21 — packages preview. Governance metadata only; no billing activation. --}}

@section('content')
    <section class="hero">
        <div class="wrap">
            <h1>Paket & Harga</h1>
            <p>Pilihan paket Aish POS Lite untuk berbagai skala UMKM. Harga bersifat indikatif dan tata kelola.</p>
            <div class="note" style="max-width:640px;margin:0 auto;">
                Belum ada pendaftaran mandiri atau penagihan otomatis pada tahap ini. Aktivasi paket dilakukan oleh tim kami.
            </div>
        </div>
    </section>

    <section>
        <div class="wrap">
            @if($packages->isEmpty())
                <p class="muted" style="text-align:center;">Detail paket akan segera tersedia. Silakan
                    <a href="/#interest">ajukan minat</a> untuk informasi paket terbaru.</p>
            @else
                <div class="grid">
                    @foreach($packages as $package)
                        <div class="card">
                            <h3>{{ $package->name }}</h3>
                            <div class="price">Rp {{ number_format((int) $package->monthly_price, 0, ',', '.') }}<span class="muted" style="font-size:.9rem;font-weight:400;">/bln</span></div>
                            <p class="muted" style="margin:8px 0;">Segmen: {{ $package->target_segment }}</p>
                            <p class="muted">Batas perangkat: {{ $package->device_limit ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="text-align:center;margin-top:28px;">
                <a class="btn" href="/#interest">Ajukan Minat</a>
            </div>
        </div>
    </section>
@endsection
