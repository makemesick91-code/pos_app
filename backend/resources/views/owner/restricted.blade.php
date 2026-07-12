@extends('owner.layout')

@section('title', 'Akses Dibatasi')

@section('content')
    <div class="breadcrumb"><a href="{{ route('owner.dashboard') }}">Dashboard</a> · Akses Dibatasi</div>
    <h1 class="page-title">Akses dibatasi</h1>

    <div class="notice bad" role="status">
        <strong>Bisnis Anda sedang tidak dapat mengakses data operasional.</strong>
        <p style="margin:.5rem 0 0;">
            Status langganan bisnis Anda saat ini:
            @include('owner.partials.lifecycle-badge', ['lifecycle' => $context->lifecycle])
        </p>
        @if($context->lifecycle->reason)
            <p style="margin:.5rem 0 0;">{{ $context->lifecycle->reason }}</p>
        @endif
    </div>

    <div class="panel">
        <div class="panel-body">
            <p>Halaman data bisnis (outlet dan perangkat) tidak tersedia selama status ini berlaku.
               Anda tetap dapat meninjau status langganan dan tagihan untuk memulihkan akses.</p>
            <p style="margin-top:var(--aish-space-md);">
                <a href="{{ route('owner.subscription') }}">Lihat Langganan &amp; Tagihan</a>
            </p>
        </div>
    </div>
@endsection
