@extends('admin.layout')

@section('title', 'Insiden')

@section('content')
    <div class="breadcrumb">Insiden</div>
    <h1 class="page-title">Insiden platform</h1>

    <form method="GET" action="{{ route('admin.incidents.index') }}" class="filters">
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                @foreach(\App\Models\ProductionIncident::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="severity">Severity</label>
            <select id="severity" name="severity">
                <option value="">Semua</option>
                @foreach(\App\Models\ProductionIncident::SEVERITIES as $s)
                    <option value="{{ $s }}" @selected(($filters['severity'] ?? '') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="open_only">Hanya terbuka</label>
            <select id="open_only" name="open_only">
                <option value="0" @selected(! ($filters['open_only'] ?? false))>Tidak</option>
                <option value="1" @selected($filters['open_only'] ?? false)>Ya</option>
            </select>
        </div>
        <div><button type="submit" class="btn-ghost">Terapkan</button></div>
    </form>

    <div class="panel">
        <div class="panel-body">
            @if(count($rows) === 0)
                <div class="empty">Tidak ada insiden yang cocok.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Referensi</th>
                                <th scope="col">Severity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Area</th>
                                <th scope="col">Dampak</th>
                                <th scope="col">Terdeteksi</th>
                                <th scope="col">SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $row)
                                @php $model = $paginator->items()[$i]; @endphp
                                <tr>
                                    <td><a href="{{ route('admin.incidents.show', $model->id) }}">{{ $row['reference'] }}</a></td>
                                    <td>@include('support.partials.status-badge', ['status' => $row['severity']])</td>
                                    <td>@include('support.partials.status-badge', ['status' => $row['status']])</td>
                                    <td>{{ $row['area'] ?? '—' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($row['impact'] ?? '—', 60) }}</td>
                                    <td>{{ $row['detected_at'] ?? '—' }}</td>
                                    <td>@if($row['sla_breached'])<span class="badge badge-bad">Terlampaui</span>@else<span class="badge badge-neutral">—</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $paginator->links('billing.partials.pager') }}
            @endif
        </div>
    </div>
@endsection
