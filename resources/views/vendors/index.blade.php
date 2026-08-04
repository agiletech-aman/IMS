@extends('layouts.app')
@section('title','Vendors & OEM')
@section('content')
@include('partials.page-header',['title'=>'Vendors & OEM','description'=>'Maintain supplier contacts, service performance, and active agreements.'])

<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-handshake','label'=>'Total Vendors','value'=>number_format($stats['total'])])
    @include('partials.stat-card',['icon'=>'fa-file-signature','label'=>'Active Contracts','value'=>number_format($stats['active_contracts']),'class'=>'success'])
    @include('partials.stat-card',['icon'=>'fa-clock','label'=>'Renewals Due','value'=>number_format($stats['renewals_due']),'class'=>'warning'])
    @include('partials.stat-card',['icon'=>'fa-star','label'=>'Preferred Partners','value'=>number_format($stats['preferred']),'class'=>'info'])
</div>

<div class="panel">
    <div class="panel-header">
        <div><h2>Vendor / OEM Directory</h2><p>{{ $vendors->total() }} records available</p></div>
        @permission('vendors','create')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVendorModal"><i class="fa-solid fa-plus me-2"></i>Add Vendor / OEM</button>
        @endpermission
    </div>
    <div class="table-toolbar">
        <form class="vendor-filters" method="GET">
            <div class="table-search"><i class="fa-solid fa-magnifying-glass"></i><input name="search" type="search" value="{{ request('search') }}" placeholder="Search vendor, code or contact…"></div>
            <select class="form-select" name="type" aria-label="Filter vendor type" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach(['Vendor','OEM','Vendor & OEM'] as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach
            </select>
        </form>
        @if(request('search') || request('type'))<a class="btn btn-soft" href="{{ route('vendors.index') }}"><i class="fa-solid fa-xmark me-2"></i>Clear</a>@endif
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Vendor / OEM</th><th>Contact</th><th>Category</th><th>Contract</th><th>Renewal</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($vendors as $vendor)
                <tr>
                    <td><div class="cell-title">
                        <span class="mini-icon"><i class="fa-solid {{ $vendor->vendor_type === 'OEM' ? 'fa-industry' : 'fa-handshake' }}"></i></span>
                        <span><strong>{{ $vendor->name }} @if($vendor->preferred)<i class="fa-solid fa-star text-warning ms-1" title="Preferred partner"></i>@endif</strong><small>{{ $vendor->code }} · {{ $vendor->vendor_type }}</small></span>
                    </div></td>
                    <td><strong>{{ $vendor->contact_person ?: '—' }}</strong><small class="d-block text-secondary">{{ $vendor->email ?: ($vendor->phone ?: 'No contact') }}</small></td>
                    <td>{{ $vendor->category ?: '—' }}</td>
                    <td><span class="badge-soft {{ $vendor->amc_status === 'Active' ? 'success' : ($vendor->amc_status === 'Expired' ? 'danger' : ($vendor->amc_status === 'Renewal Due' ? 'warning' : 'muted')) }}">{{ $vendor->amc_status }}</span></td>
                    <td>{{ $vendor->contract_end?->format('d M Y') ?: '—' }}</td>
                    <td>@if($vendor->rating !== null)<i class="fa-solid fa-star text-warning me-1"></i>{{ number_format((float) $vendor->rating, 1) }}@else — @endif</td>
                    <td><span class="badge-soft {{ $vendor->status === 'Active' ? 'success' : 'muted' }}">{{ $vendor->status }}</span></td>
                    <td>
                        @permission('vendors','update')
                            <button class="btn btn-soft btn-icon" title="Edit vendor" data-bs-toggle="modal" data-bs-target="#editVendorModal{{ $vendor->id }}"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endpermission
                        @permission('vendors','delete')
                        <form class="d-inline" method="POST" action="{{ route('vendors.destroy', $vendor) }}" data-confirm data-confirm-title="Delete Vendor / OEM?" data-confirm-message="This will permanently delete {{ $vendor->name }}." data-confirm-label="Delete Vendor">@csrf @method('DELETE')<button class="btn btn-soft btn-icon" title="Delete vendor"><i class="fa-regular fa-trash-can text-danger"></i></button></form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5 text-secondary"><i class="fa-solid fa-handshake fa-2x mb-3 d-block"></i>No vendors or OEMs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$vendors])
</div>

@permission('vendors','create')
    @include('vendors.form',['vendor'=>null])
@endpermission
@permission('vendors','update')
    @foreach($vendors as $vendor)
        @include('vendors.form',['vendor'=>$vendor])
    @endforeach
@endpermission
@endsection
