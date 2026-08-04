@extends('layouts.app')
@section('title','Complaint Management')
@section('content')
@php
    $statusStyles = [
        'Complaint Raised' => 'danger', 'Engineer Assigned' => 'info',
        'Engineer Visit' => 'warning', 'Work in Progress' => 'warning',
        'Resolved' => 'success', 'Closed' => 'muted',
    ];
@endphp
@include('partials.page-header',[
    'title'=>'Complaint Management',
    'description'=>'Track service complaints from initial report through engineer action and closure.',
])

<div class="complaint-summary-grid mb-3">
    @foreach($statuses as $status)
        @include('partials.stat-card',[
            'icon'=>['fa-circle-exclamation','fa-user-check','fa-location-dot','fa-gears','fa-circle-check','fa-lock'][$loop->index],
            'label'=>$status,
            'value'=>number_format($statusCounts[$status] ?? 0),
            'class'=>$statusStyles[$status],
        ])
    @endforeach
</div>

<div class="panel">
    <div class="panel-header">
        <div><h2>Service Complaints</h2><p>{{ $complaints->total() }} complaint records</p></div>
        <div class="d-flex flex-wrap gap-2">
            @permission('complaints','export')
                <a class="btn btn-soft" href="{{ route('complaints.export',request()->only(['search','status','priority'])) }}"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a>
            @endpermission
            @permission('complaints','create')
                <a class="btn btn-primary" href="{{ route('complaints.create') }}"><i class="fa-solid fa-plus me-2"></i>Raise Complaint</a>
            @endpermission
        </div>
    </div>
    <div class="table-toolbar">
        <form class="complaint-filters" method="GET">
            <div class="table-search"><i class="fa-solid fa-magnifying-glass"></i><input name="search" type="search" value="{{ request('search') }}" placeholder="Search number, subject or requester…"></div>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All stages</option>
                @foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach
            </select>
            <select class="form-select" name="priority" onchange="this.form.submit()">
                <option value="">All priorities</option>
                @foreach($priorities as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>@endforeach
            </select>
        </form>
        @if(request('search') || request('status') || request('priority'))
            <a class="btn btn-soft" href="{{ route('complaints.index') }}"><i class="fa-solid fa-xmark me-2"></i>Clear</a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Complaint</th><th>Requester</th><th>Category</th><th>Priority</th><th>Engineer</th><th>Stage</th><th>Raised</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($complaints as $complaint)
                <tr>
                    <td><a href="{{ route('complaints.show',$complaint) }}"><strong>{{ $complaint->complaint_number }}</strong></a><small class="d-block text-secondary">{{ \Illuminate\Support\Str::limit($complaint->subject, 42) }}</small></td>
                    <td><strong>{{ $complaint->requester_name }}</strong><small class="d-block text-secondary">{{ $complaint->requester_email ?: ($complaint->requester_contact ?: 'No contact') }}</small></td>
                    <td>{{ $complaint->category }}@if($complaint->asset)<small class="d-block text-secondary">{{ $complaint->asset->asset_tag }}</small>@endif</td>
                    <td><span class="badge-soft {{ $complaint->priority === 'Critical' ? 'danger' : ($complaint->priority === 'High' ? 'warning' : '') }}">{{ $complaint->priority }}</span></td>
                    <td>{{ $complaint->engineer?->name ?? 'Unassigned' }}</td>
                    <td><span class="badge-soft {{ $statusStyles[$complaint->status] ?? '' }}">{{ $complaint->status }}</span></td>
                    <td>{{ $complaint->created_at->format('d M Y') }}<small class="d-block text-secondary">{{ $complaint->created_at->format('h:i A') }}</small></td>
                    <td>
                        <a class="btn btn-soft btn-icon" href="{{ route('complaints.show',$complaint) }}" title="View complaint"><i class="fa-regular fa-eye"></i></a>
                        @permission('complaints','delete')
                            <form class="d-inline" method="POST" action="{{ route('complaints.destroy',$complaint) }}" data-confirm data-confirm-title="Delete complaint?" data-confirm-message="This permanently deletes {{ $complaint->complaint_number }} and its history." data-confirm-label="Delete Complaint">@csrf @method('DELETE')<button class="btn btn-soft btn-icon" title="Delete"><i class="fa-regular fa-trash-can text-danger"></i></button></form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5 text-secondary"><i class="fa-solid fa-screwdriver-wrench fa-2x mb-3 d-block"></i>No complaints found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$complaints])
</div>
@endsection
