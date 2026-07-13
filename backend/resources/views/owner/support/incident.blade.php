@extends('owner.layout')

@section('title', 'Detail insiden')

@section('content')
    <div class="breadcrumb"><a href="{{ route('owner.support') }}">Dukungan</a> · {{ $incident['incident_number'] }}</div>
    <h1 class="page-title">{{ $incident['title'] ?: $incident['incident_number'] }}</h1>

    <div class="cards">
        <div class="card">
            <div class="k">Severity</div>
            <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $incident['severity']])</div>
        </div>
        <div class="card">
            <div class="k">Status</div>
            <div class="v" style="font-size:18px;">@include('support.partials.status-badge', ['status' => $incident['status']])</div>
        </div>
        <div class="card">
            <div class="k">Kategori</div>
            <div class="v" style="font-size:18px;">{{ $incident['category'] ?? '—' }}</div>
        </div>
    </div>

    <div class="panel">
        <h2>Ringkasan</h2>
        <div class="panel-body">
            <p style="white-space:pre-wrap;">{{ $incident['summary'] ?: '—' }}</p>
            <dl class="kv" style="margin-top:var(--aish-space-lg);">
                <dt>Dibuka</dt><dd>{{ $incident['opened_at'] ?? '—' }}</dd>
                <dt>Diselesaikan</dt><dd>{{ $incident['resolved_at'] ?? '—' }}</dd>
                <dt>Ditutup</dt><dd>{{ $incident['closed_at'] ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="panel">
        <div class="panel-body">
            <p style="color:var(--aish-text-secondary);font-size:13px;">
                Hanya informasi yang relevan untuk bisnis Anda yang ditampilkan. Catatan internal dan detail teknis tidak disertakan.
            </p>
        </div>
    </div>
@endsection
