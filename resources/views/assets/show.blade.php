@extends('layouts.app')
@section('title','Asset Details')
@section('content')
@include('partials.page-header',['title'=>$asset->name,'description'=>$asset->asset_tag.' · Updated '.$asset->updated_at->diffForHumans()] + (app(\App\Services\PermissionService::class)->allows('assets','update') ? ['actionUrl'=>route('assets.edit',$asset),'actionLabel'=>'Edit Asset','actionIcon'=>'fa-pen'] : []))
<nav class="nav nav-pills mb-3" id="assetShowTabs" role="tablist">
    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#assetOverviewTab" type="button"><i class="fa-solid fa-circle-info me-2"></i>Overview</button>
    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#assetHistoryTab" type="button"><i class="fa-solid fa-clock-rotate-left me-2"></i>History</button>
</nav>
<div class="tab-content">
<div class="tab-pane fade show active" id="assetOverviewTab">
<div class="row g-3"><div class="col-xl-8"><div class="panel mb-3">
    <div class="panel-header"><h2>Asset Profile</h2><span class="badge-soft {{ $asset->status === 'Active' ? 'success' : '' }}">{{ $asset->status }}</span></div>
    @if($asset->image_path)<div class="panel-body pb-0"><img src="{{ \App\Support\PublicUrl::storage($asset->image_path) }}" alt="{{ $asset->name }}" style="width:100%;max-height:320px;object-fit:contain;border-radius:12px;background:var(--bg-secondary)"></div>@endif
<div class="detail-list">@foreach([
        'Asset ID'=>$asset->asset_tag,'Type'=>$asset->type?->name,'Subtype'=>$asset->subtype?->name,'Brand'=>$asset->brand?->name,
        'Serial Number'=>$asset->serial_number,'FR Number'=>$asset->fr_number,'Department'=>$asset->department?->name,'Sub Department'=>$asset->subDepartment?->name,
        'Installation Date'=>$asset->installation_date?->format('d M Y'),
        'Assigned To'=>$asset->assigned_to ?: 'Unassigned','Warranty Expiry'=>$asset->warranty_expiry?->format('d M Y'),'AMC Expiry'=>$asset->amc_expiry?->format('d M Y'),
        'Created At'=>$asset->created_at?->format('d M Y, h:i A'),'Allotment Date'=>$asset->updated_at?->format('d M Y, h:i A')
    ] as $label=>$value)<div class="detail-item"><span>{{ $label }}</span><strong>{{ $value ?: '—' }}</strong></div>@endforeach
@foreach($asset->subtypeParameterValues() as $param)<div class="detail-item"><span>{{ $param['label'] }}</span><strong>{{ $param['value'] ?: '—' }}</strong></div>@endforeach
@foreach($asset->subtypeFieldValues() as $subtypeField)<div class="detail-item"><span>{{ $subtypeField['label'] }}</span><strong>{{ $subtypeField['value'] ?: '—' }}</strong></div>@endforeach</div>
</div></div><div class="col-xl-4"><div class="panel mb-3"><div class="panel-header"><h2>Description</h2></div><div class="panel-body"><p class="mb-0 text-secondary">{{ $asset->notes ?: 'No description added.' }}</p></div></div>
@permission('assets','delete')<div class="panel"><div class="panel-header"><h2>Danger Zone</h2></div><div class="panel-body"><form method="POST" action="{{ route('assets.destroy',$asset) }}" data-confirm data-confirm-title="Delete Asset?" data-confirm-message="This will permanently delete {{ $asset->name }} ({{ $asset->asset_tag }}). This action cannot be undone." data-confirm-label="Delete Asset">@csrf @method('DELETE')<button class="btn btn-outline-danger w-100"><i class="fa-regular fa-trash-can me-2"></i>Delete Asset</button></form></div></div>@endpermission</div></div>
</div>
<div class="tab-pane fade" id="assetHistoryTab">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h5 mb-1">Asset History</h2>
        <p class="mb-0 text-secondary small">Assignment trail and status changes for this asset.</p>
    </div>
    @permission('assets','export')
    <div class="d-flex gap-2">
        <a class="btn btn-soft btn-sm" href="{{ route('assets.history.export',$asset) }}"><i class="fa-solid fa-file-csv me-2 text-success"></i>Export CSV</a>
        <a class="btn btn-soft btn-sm" href="{{ route('assets.history.export.xlsx',$asset) }}"><i class="fa-solid fa-file-excel me-2 text-success"></i>Export Excel</a>
    </div>
    @endpermission
</div>

<div class="panel mb-3">
    <div class="panel-header"><div><h2>Assignment History</h2><p class="mb-0 text-secondary small">Who this asset was assigned to, and when.</p></div></div>
    <div class="table-responsive">
        <table class="table data-table mb-0">
            <thead><tr><th>Assigned To</th><th>Assigned At</th><th>Assigned By</th><th>Unassigned At</th><th>Unassigned By</th></tr></thead>
            <tbody>
                @forelse($assignmentHistory as $entry)
                <tr>
                    <td>
                        <strong>{{ $entry->faculty?->name ?? '—' }}</strong>
                        @unless($entry->unassigned_at)
                            <span class="badge-soft success ms-2">Current</span>
                        @endunless
                    </td>
                    <td>{{ $entry->assigned_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>{{ $entry->assigned_by ?? '—' }}</td>
                    <td>{{ $entry->unassigned_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>{{ $entry->unassigned_by ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5"><div class="empty-report-state"><i class="fa-solid fa-user-clock"></i><strong>No assignment history yet</strong><span>This asset hasn't been assigned to anyone so far.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><div><h2>Status Changes</h2><p class="mb-0 text-secondary small">When this asset's status or assignment changed.</p></div></div>
    <div class="table-responsive">
        <table class="table data-table mb-0">
            <thead><tr><th>Date</th><th>Changed By</th><th>Change</th></tr></thead>
            <tbody>
                @forelse($activityLog as $log)
                @php
                    $statusChanged = array_key_exists('status', $log->new_values ?? []);
                    $assignmentChanged = array_key_exists('assigned_to', $log->new_values ?? []);
                    $newAssignee = $log->new_values['assigned_to'] ?? null;
                @endphp
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                    <td>{{ $log->actor_name ?? 'System' }}</td>
                    <td>
                        <div class="d-flex flex-column gap-1 align-items-start">
                            @if($statusChanged)
                                <span class="badge-soft warning">
                                    <i class="fa-solid fa-rotate me-1"></i>
                                    {{ $log->old_values['status'] ?? '—' }} → {{ $log->new_values['status'] }}
                                </span>
                            @endif
                            @if($assignmentChanged)
                                @if($newAssignee)
                                    <span class="badge-soft success"><i class="fa-solid fa-user-plus me-1"></i>Assigned to {{ $newAssignee }}</span>
                                @else
                                    <span class="badge-soft muted"><i class="fa-solid fa-user-minus me-1"></i>Unassigned from {{ $log->old_values['assigned_to'] ?? 'previous user' }}</span>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3"><div class="empty-report-state"><i class="fa-solid fa-clock-rotate-left"></i><strong>No status changes yet</strong><span>Status and assignment changes for this asset will show up here.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
@endsection
