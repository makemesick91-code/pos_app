@extends('public-website.layout')

{{-- Sprint 21 — landing page. Interest-only CTA; no self-service signup. --}}

@section('content')
    <section class="hero">
        <div class="wrap">
            <h1>{{ $version->headline ?? 'Kasir Android ringan untuk UMKM Anda' }}</h1>
            <p>{{ $version->subheadline ?? 'Aish POS Lite membantu warung, toko kecil, dan kedai berjualan lebih cepat: QRIS, tunai, mode offline, cetak struk, stok sederhana, dan laporan harian.' }}</p>
            <a class="btn" href="{{ $version->hero_cta_target ?? '#interest' }}">{{ $version->hero_cta_label ?? 'Ajukan Minat' }}</a>
            <a class="btn secondary" href="/packages">Lihat Paket</a>
        </div>
    </section>

    <section>
        <div class="wrap">
            <h2>Fitur inti</h2>
            <div class="grid">
                <div class="card"><h3>Pembayaran QRIS</h3><p>Terima QRIS online yang diproses aman di backend.</p></div>
                <div class="card"><h3>Tunai & Offline</h3><p>Transaksi tunai tetap jalan tanpa internet, lalu tersinkron otomatis.</p></div>
                <div class="card"><h3>Cetak Struk</h3><p>Struk termal ESC/POS langsung dari perangkat Android.</p></div>
                <div class="card"><h3>Stok Sederhana</h3><p>Pergerakan stok tercatat otomatis dari penjualan.</p></div>
                <div class="card"><h3>Laporan Harian</h3><p>Tutup kasir harian dan laporan penjualan yang rapi.</p></div>
                <div class="card"><h3>Multi-perangkat</h3><p>Kelola beberapa perangkat sesuai batas paket langganan.</p></div>
            </div>
        </div>
    </section>

    <section>
        <div class="wrap">
            <h2>Cocok untuk</h2>
            <div class="grid">
                @foreach(($version->target_segments ?? ['Warung','Toko Kecil','Kedai','Laundry','Retail','Apotek ringan']) as $segment)
                    <div class="card"><h3>{{ is_array($segment) ? ($segment['label'] ?? reset($segment)) : $segment }}</h3></div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="interest">
        <div class="wrap">
            <h2>Ajukan Minat</h2>
            <p class="muted" style="text-align:center; max-width:560px; margin:0 auto 18px;">
                Formulir ini hanya untuk menyatakan minat. Tim kami akan menghubungi Anda untuk aktivasi.
                Tidak ada pembuatan akun atau penagihan otomatis.
            </p>

            @if ($errors->any())
                <div class="errors" style="max-width:560px;margin:0 auto 14px;">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="lead" method="POST" action="/interest">
                @csrf
                <label for="contact_name">Nama *</label>
                <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required>

                <label for="contact_email">Email *</label>
                <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" required>

                <label for="contact_phone">Nomor WhatsApp</label>
                <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}">

                <label for="business_name">Nama Usaha</label>
                <input id="business_name" name="business_name" value="{{ old('business_name') }}">

                <label for="business_type">Jenis Usaha</label>
                <input id="business_type" name="business_type" value="{{ old('business_type') }}" placeholder="Warung, Toko, Kedai, ...">

                <label for="message">Pesan</label>
                <textarea id="message" name="message" rows="3">{{ old('message') }}</textarea>

                <div class="consent">
                    <input id="consent" type="checkbox" name="consent" value="1" required>
                    <label for="consent" style="margin:0;font-weight:400;">
                        Saya setuju data ini digunakan untuk menghubungi saya terkait Aish POS Lite.
                    </label>
                </div>

                <button class="btn" type="submit">Kirim Minat</button>
            </form>
        </div>
    </section>
@endsection
