@extends('layouts.app')
@section('title','Asset Details')
@section('content')
@include('partials.page-header',['title'=>$asset->name,'description'=>$asset->asset_tag.' · Updated '.$asset->updated_at->diffForHumans()] + (app(\App\Services\PermissionService::class)->allows('assets','update') ? ['actionUrl'=>route('assets.edit',$asset),'actionLabel'=>'Edit Asset','actionIcon'=>'fa-pen'] : []))
<div class="row g-3"><div class="col-xl-8"><div class="panel mb-3">
    <div class="panel-header"><h2>Asset Profile</h2><span class="badge-soft {{ $asset->status === 'Active' ? 'success' : '' }}">{{ $asset->status }}</span></div>
    @if($asset->image_path)<div class="panel-body pb-0"><img src="{{ \App\Support\PublicUrl::storage($asset->image_path) }}" alt="{{ $asset->name }}" style="width:100%;max-height:320px;object-fit:contain;border-radius:12px;background:var(--bg-secondary)"></div>@endif
    <div class="detail-list">@foreach([
        'Asset ID'=>$asset->asset_tag,'Category'=>$asset->category?->name,'Type'=>$asset->type?->name,'Brand'=>$asset->brand?->name,
        'Model'=>$asset->model,'Serial Number'=>$asset->serial_number,'Department'=>$asset->department?->name,'Sub Department'=>$asset->subDepartment?->name,
        'Purchase Date'=>$asset->purchase_date?->format('d M Y'),'Installation Date'=>$asset->installation_date?->format('d M Y'),'Location'=>$asset->location,
        'Assigned To'=>$asset->assigned_to ?: 'Unassigned','Warranty Expiry'=>$asset->warranty_expiry?->format('d M Y'),'AMC Expiry'=>$asset->amc_expiry?->format('d M Y')
    ] as $label=>$value)<div class="detail-item"><span>{{ $label }}</span><strong>{{ $value ?: '—' }}</strong></div>@endforeach</div>
</div></div><div class="col-xl-4"><div class="panel mb-3"><div class="panel-header"><h2>Notes</h2></div><div class="panel-body"><p class="mb-0 text-secondary">{{ $asset->notes ?: 'No notes added.' }}</p></div></div>
@permission('assets','delete')<div class="panel"><div class="panel-header"><h2>Danger Zone</h2></div><div class="panel-body"><form method="POST" action="{{ route('assets.destroy',$asset) }}" data-confirm data-confirm-title="Delete Asset?" data-confirm-message="This will permanently delete {{ $asset->name }} ({{ $asset->asset_tag }}). This action cannot be undone." data-confirm-label="Delete Asset">@csrf @method('DELETE')<button class="btn btn-outline-danger w-100"><i class="fa-regular fa-trash-can me-2"></i>Delete Asset</button></form></div></div>@endpermission</div></div>
@endsection
