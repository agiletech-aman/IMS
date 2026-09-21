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
<div class="panel mb-3">
    <div class="panel-header"><h2>Assignment History</h2><p class="mb-0 text-secondary">Who this asset was assigned to, and when.</p></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Assigned To</th><th>Assigned At</th><th>Assigned By</th><th>Unassigned At</th><th>Unassigned By</th></tr></thead>
            <tbody>
                @forelse($assignmentHistory as $entry)
                <tr>
                    <td>{{ $entry->faculty?->name ?? '—' }}</td>
                    <td>{{ $entry->assigned_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>{{ $entry->assigned_by ?? '—' }}</td>
                    <td>{{ $entry->unassigned_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>{{ $entry->unassigned_by ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-secondary py-4">No assignment history recorded for this asset yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2>Status Changes</h2><p class="mb-0 text-secondary">When this asset's status or assignment changed.</p></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Changed By</th><th>Change</th></tr></thead>
            <tbody>
                @forelse($activityLog as $log)
                <tr>
                    <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
                    <td>{{ $log->actor_name ?? 'System' }}</td>
                    <td>
                        @if(array_key_exists('status', $log->new_values ?? []))
                            Status changed from <strong>{{ $log->old_values['status'] ?? '—' }}</strong> to <strong>{{ $log->new_values['status'] }}</strong>
                        @endif
                        @if(array_key_exists('assigned_to', $log->new_values ?? []))
                            @if(array_key_exists('status', $log->new_values ?? []))<br>@endif
                            @php $newAssignee = $log->new_values['assigned_to'] ?? null; @endphp
                            @if($newAssignee)
                                Assigned to <strong>{{ $newAssignee }}</strong>
                            @else
                                Unassigned from <strong>{{ $log->old_values['assigned_to'] ?? 'previous user' }}</strong>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-secondary py-4">No status changes recorded for this asset yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
@endsection
