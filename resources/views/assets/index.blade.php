@extends('layouts.app')
@section('title','Assets')
@section('content')
@include('partials.page-header',['title'=>'Asset Management','description'=>'Track the complete lifecycle of every enterprise IT asset.'] + (app(\App\Services\PermissionService::class)->allows('assets','create') ? ['actionUrl'=>route('assets.create'),'actionLabel'=>'Add Asset'] : []))
<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-cubes','label'=>'Total Assets','value'=>number_format($stats['total'])])
    @include('partials.stat-card',['icon'=>'fa-circle-check','label'=>'Assigned','value'=>number_format($stats['assigned']),'class'=>'success'])
    @include('partials.stat-card',['icon'=>'fa-box-open','label'=>'In Stock','value'=>number_format($stats['stock']),'class'=>'info'])
    @include('partials.stat-card',['icon'=>'fa-screwdriver-wrench','label'=>'Maintenance','value'=>number_format($stats['maintenance']),'class'=>'warning'])
</div>
<div class="panel">
    <div class="table-toolbar">
        <form class="table-search" method="GET"><i class="fa-solid fa-magnifying-glass"></i><input name="search" type="search" value="{{ request('search') }}" placeholder="Search asset ID, name, serial…"></form>
        @if(request('search'))<a class="btn btn-soft" href="{{ route('assets.index') }}"><i class="fa-solid fa-xmark me-2"></i>Clear</a>@endif
        <div class="table-actions">
            @permission('assets','export')
            <a href="{{ route('assets.export') }}" class="btn btn-soft"><i class="fa-solid fa-file-export me-2"></i>Export</a>
            @endpermission
            @permission('assets','import')
            <button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#assetImportModal"><i class="fa-solid fa-file-import me-2"></i>Import</button>
            @endpermission
        </div>
    </div>
<div class="table-responsive"><table class="table data-table"><thead><tr><th>Asset</th><th>Type</th><th>Serial Number</th><th>FR Number</th><th>Department</th><th>Installation Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($assets as $asset)
        <tr>
            <td><div class="cell-title">
                @if($asset->image_path)<img src="{{ \App\Support\PublicUrl::storage($asset->image_path) }}" alt="{{ $asset->name }}" style="width:40px;height:40px;object-fit:cover;border-radius:9px">
                @else<span class="mini-icon"><i class="fa-solid fa-laptop"></i></span>@endif
                <span><strong>{{ $asset->name }}</strong><small>#{{ $asset->asset_tag }}</small></span>
            </div></td>
            <td>{{ $asset->type?->name ?: '—' }}</td><td>{{ $asset->serial_number ?: '—' }}</td><td>{{ $asset->fr_number ?: '—' }}</td><td>{{ $asset->department?->name ?: '—' }}</td><td>{{ $asset->installation_date?->format('d M Y') ?? '—' }}</td>
            <td><span class="badge-soft {{ $asset->status === 'Active' ? 'success' : ($asset->status === 'Under Maintenance' ? 'warning' : '') }}">{{ $asset->status }}</span></td>
            <td>
                <a class="btn btn-soft btn-icon" href="{{ route('assets.show',$asset) }}" title="View asset"><i class="fa-regular fa-eye"></i></a>
                @permission('assets','update')
                    <a class="btn btn-soft btn-icon" href="{{ route('assets.edit',$asset) }}" title="Edit asset"><i class="fa-regular fa-pen-to-square"></i></a>
                @endpermission
                @permission('assets','delete')
                    <form class="d-inline" method="POST" action="{{ route('assets.destroy',$asset) }}"
                          data-confirm
                          data-confirm-title="Delete Asset?"
                          data-confirm-message="This will permanently delete {{ $asset->name }} ({{ $asset->asset_tag }}). This action cannot be undone."
                          data-confirm-label="Delete Asset">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-soft btn-icon" type="submit" title="Delete asset" aria-label="Delete {{ $asset->name }}">
                            <i class="fa-regular fa-trash-can text-danger"></i>
                        </button>
                    </form>
                @endpermission
            </td>
        </tr>
    @empty<tr><td colspan="8" class="text-center py-5 text-secondary"><i class="fa-solid fa-box-open fa-2x mb-3 d-block"></i>No assets found. Add your first asset to get started.</td></tr>@endforelse
    </tbody></table></div>
@include('partials.pagination',['paginator'=>$assets])
</div>

@permission('assets','import')
    @include('partials.asset-import-modal')
@endpermission
@endsection

@if(app(\App\Services\PermissionService::class)->allows('assets','import') && (session('openAssetImportModal') || $errors->assetImport->any()))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('assetImportModal')).show();
});
</script>
@endpush
@endif
