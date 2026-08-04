@extends('layouts.app')
@section('title',$complaint->complaint_number)
@section('content')
@php
    $currentIndex = array_search($complaint->status, $statuses, true);
    $nextStatus = $complaint->nextStatus();
    $statusClass = match($complaint->status) {
        'Complaint Raised' => 'danger',
        'Engineer Assigned' => 'info',
        'Engineer Visit', 'Work in Progress' => 'warning',
        'Resolved' => 'success',
        default => 'muted',
    };
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Service Management</p><h1>{{ $complaint->complaint_number }} · {{ $complaint->subject }}</h1><p>Raised by {{ $complaint->requester_name }} on {{ $complaint->created_at->format('d M Y \a\t h:i A') }}</p></div>
    <div class="d-flex gap-2"><span class="badge-soft {{ $statusClass }} align-self-center">{{ $complaint->status }}</span><a class="btn btn-soft" href="{{ route('complaints.index') }}"><i class="fa-solid fa-arrow-left me-2"></i>All Complaints</a></div>
</div>

<div class="panel complaint-workflow mb-3">
    <div class="panel-header"><div><h2>Complaint Workflow</h2><p>Stages progress in sequence from raised to closed</p></div></div>
    <div class="panel-body">
        <div class="workflow-track">
            @foreach($statuses as $status)
                <div class="workflow-step {{ $loop->index < $currentIndex ? 'is-complete' : ($loop->index === $currentIndex ? 'is-current' : '') }}">
                    <span class="workflow-marker"><i class="fa-solid {{ $loop->index < $currentIndex ? 'fa-check' : ['fa-circle-exclamation','fa-user-check','fa-location-dot','fa-gears','fa-circle-check','fa-lock'][$loop->index] }}"></i></span>
                    <strong>{{ $status }}</strong>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="panel mb-3">
            <div class="panel-header"><h2>Issue Description</h2><span class="badge-soft {{ $complaint->priority === 'Critical' ? 'danger' : ($complaint->priority === 'High' ? 'warning' : '') }}">{{ $complaint->priority }} priority</span></div>
            <div class="panel-body"><p class="mb-0 complaint-description">{{ $complaint->description }}</p></div>
        </div>

        @if($complaint->resolution_notes)
            <div class="panel mb-3">
                <div class="panel-header"><h2>Resolution</h2><span class="badge-soft success"><i class="fa-solid fa-check me-1"></i>Resolved</span></div>
                <div class="panel-body"><p class="mb-0 complaint-description">{{ $complaint->resolution_notes }}</p></div>
            </div>
        @endif

        <div class="panel">
            <div class="panel-header"><div><h2>Activity History</h2><p>A complete audit trail of workflow changes</p></div></div>
            <div class="panel-body">
                <ul class="timeline mb-0">
                    @forelse($complaint->activities as $activity)
                        <li><span class="timeline-dot"></span><div><p><strong>{{ $activity->to_status }}</strong>@if($activity->from_status && $activity->from_status !== $activity->to_status)<span class="text-secondary"> from {{ $activity->from_status }}</span>@endif</p><small>{{ $activity->created_at->format('d M Y, h:i A') }} · {{ $activity->performed_by ?: 'System' }}</small>@if($activity->note)<p class="text-secondary mt-1">{{ $activity->note }}</p>@endif</div></li>
                    @empty
                        <li><span class="timeline-dot"></span><div><p>No activity recorded.</p></div></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel mb-3">
            <div class="panel-header"><h2>Complaint Details</h2></div>
            <div class="detail-list" style="grid-template-columns:1fr">
                @foreach([
                    'Category'=>$complaint->category,
                    'Related Asset'=>$complaint->asset ? $complaint->asset->asset_tag.' · '.$complaint->asset->name : '—',
                    'Requester Email'=>$complaint->requester_email ?: '—',
                    'Requester Contact'=>$complaint->requester_contact ?: '—',
                    'Engineer'=>$complaint->engineer?->name ?? 'Unassigned',
                    'Visit Scheduled'=>$complaint->visit_scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled',
                ] as $label=>$value)
                    <div class="detail-item"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                @endforeach
            </div>
        </div>

        @permission('complaints','assign')
            @if(!in_array($complaint->status,['Resolved','Closed']))
                <div class="panel mb-3">
                    <div class="panel-header"><div><h2>{{ $complaint->engineer_id ? 'Reassign Engineer' : 'Assign Engineer' }}</h2><p>Assignment moves a new complaint to Engineer Assigned</p></div></div>
                    <form method="POST" action="{{ route('complaints.assign',$complaint) }}">
                        @csrf @method('PATCH')
                        <div class="panel-body">
                            <label class="form-label">Engineer *</label>
                            <select class="form-select mb-3" name="engineer_id" required><option value="">Select engineer</option>@foreach($engineers as $engineer)<option value="{{ $engineer->id }}" @selected((int)$complaint->engineer_id === $engineer->id)>{{ $engineer->name }} · {{ $engineer->role }}</option>@endforeach</select>
                            <label class="form-label">Scheduled Visit</label><input class="form-control mb-3" type="datetime-local" name="visit_scheduled_at" value="{{ $complaint->visit_scheduled_at?->format('Y-m-d\TH:i') }}">
                            <label class="form-label">Assignment Note</label><textarea class="form-control mb-3" name="note" rows="2" placeholder="Optional instructions"></textarea>
                            <button class="btn btn-primary w-100"><i class="fa-solid fa-user-check me-2"></i>{{ $complaint->engineer_id ? 'Update Assignment' : 'Assign Engineer' }}</button>
                        </div>
                    </form>
                </div>
            @endif
        @endpermission

        @permission('complaints','update')
            @if($nextStatus)
                <div class="panel">
                    <div class="panel-header"><div><h2>Progress Complaint</h2><p>Next stage: {{ $nextStatus }}</p></div></div>
                    <form method="POST" action="{{ route('complaints.status',$complaint) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                        <div class="panel-body">
                            @if(!$complaint->engineer_id)<div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-2"></i>Assign an engineer before progressing.</div>@endif
                            @if($nextStatus === 'Resolved')
                                <label class="form-label">Resolution Notes *</label><textarea class="form-control mb-3" name="resolution_notes" rows="4" required placeholder="Describe the work completed and outcome"></textarea>
                            @endif
                            <label class="form-label">Progress Note</label><textarea class="form-control mb-3" name="note" rows="3" placeholder="Add details about this stage"></textarea>
                            <button class="btn btn-primary w-100" @disabled(!$complaint->engineer_id)><i class="fa-solid fa-arrow-right me-2"></i>Move to {{ $nextStatus }}</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="panel"><div class="panel-body text-center py-4"><span class="stat-icon mx-auto mb-3"><i class="fa-solid fa-lock"></i></span><strong class="d-block">Complaint Closed</strong><small class="text-secondary">No further workflow action is required.</small></div></div>
            @endif
        @endpermission
    </div>
</div>
@endsection
