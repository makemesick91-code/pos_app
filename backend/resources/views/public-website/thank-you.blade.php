@extends('public-website.layout')

{{-- Sprint 21 — thank-you page shown after an interest-only lead submission. --}}

@section('content')
    <section class="hero">
        <div class="wrap">
            <h1>Terima kasih!</h1>
            <p>Minat Anda sudah kami terima. Tim kami akan menghubungi Anda untuk langkah aktivasi.
               Tidak ada akun atau penagihan yang dibuat otomatis.</p>
            <a class="btn" href="/">Kembali ke Beranda</a>
            <a class="btn secondary" href="/packages">Lihat Paket</a>
        </div>
    </section>
@endsection
