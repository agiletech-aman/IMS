@extends('layouts.app')
@section('title', $title)
@section('content')
@php $permissionModule = str_replace('-', '_', $module); @endphp
@include('partials.page-header', ['title' => $title, 'description' => $description])

<div class="panel">
    <div class="panel-header">
        <div><h2>{{ $title }} Directory</h2><p>{{ $records->total() }} records available</p></div>
        @permission($permissionModule,'create')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMasterModal"><i class="fa-solid fa-plus me-2"></i>Add {{ $singular }}</button>
        @endpermission
    </div>
    <div class="table-toolbar">
        <form class="table-search" method="GET"><i class="fa-solid fa-magnifying-glass"></i><input name="search" type="search" value="{{ request('search') }}" placeholder="Search {{ strtolower($title) }}…"></form>
        @if(request('search'))<a class="btn btn-soft" href="{{ route('asset-management.'.$module.'.index') }}"><i class="fa-solid fa-xmark me-2"></i>Clear</a>@endif
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr>
                <th>{{ $singular }}</th><th>Code</th>
                @if($module === 'departments')<th>Sub Departments</th>
                @elseif($module === 'sub-departments')<th>Department</th>
                @elseif($module === 'types')<th>Category</th>
                @elseif($module === 'brands')<th>Country</th><th>Support Contact</th>
                @elseif($module === 'categories')<th>Asset Types</th><th>Description</th>
                @endif
                <th>Assets</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    <td><div class="cell-title">
                        @if($module === 'brands' && $record->logo_path)
                            <img src="{{ \App\Support\PublicUrl::storage($record->logo_path) }}" alt="{{ $record->name }} logo" style="width:36px;height:36px;object-fit:contain;border-radius:8px;background:#fff;padding:3px">
                        @else<span class="mini-icon"><i class="fa-solid {{ $icon }}"></i></span>@endif
                        <span><strong>{{ $record->name }}</strong><small>Added {{ $record->created_at->format('d M Y') }}</small></span>
                    </div></td>
                    <td>{{ $record->code }}</td>
                    @if($module === 'departments')<td>{{ $record->sub_departments_count }}</td>
                    @elseif($module === 'sub-departments')<td>{{ $record->department?->name ?: '—' }}</td>
                    @elseif($module === 'types')<td>{{ $record->category?->name ?: '—' }}</td>
                    @elseif($module === 'brands')<td>{{ $record->country ?: '—' }}</td><td>{{ $record->support_contact ?: '—' }}</td>
                    @elseif($module === 'categories')<td>{{ $record->types_count }}</td><td>{{ Str::limit($record->description, 45) ?: '—' }}</td>
                    @endif
                    <td>{{ $record->assets_count }}</td>
                    <td><span class="badge-soft {{ $record->status === 'Active' ? 'success' : 'muted' }}">{{ $record->status }}</span></td>
                    <td>
                        @permission($permissionModule,'update')
                            <button class="btn btn-soft btn-icon" title="Edit" data-bs-toggle="modal" data-bs-target="#editMasterModal{{ $record->id }}"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endpermission
                        @permission($permissionModule,'delete')
                        <form class="d-inline" method="POST" action="{{ route('asset-management.'.$module.'.destroy', $record->id) }}" data-confirm data-confirm-title="Delete {{ $singular }}?" data-confirm-message="This will delete {{ $record->name }}. Records currently in use cannot be deleted." data-confirm-label="Delete {{ $singular }}">@csrf @method('DELETE')<button class="btn btn-soft btn-icon" title="Delete"><i class="fa-regular fa-trash-can text-danger"></i></button></form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-5 text-secondary"><i class="fa-solid {{ $icon }} fa-2x mb-3 d-block"></i>No {{ strtolower($title) }} found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $records])
</div>
@permission($permissionModule,'create')
    @include('asset-masters.form', ['record' => null])
@endpermission
@permission($permissionModule,'update')
    @foreach($records as $record)
        @include('asset-masters.form', ['record' => $record])
    @endforeach
@endpermission
@endsection
