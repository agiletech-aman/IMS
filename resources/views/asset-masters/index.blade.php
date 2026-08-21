@extends('layouts.app')

@section('title', $title)

@section('content')

@php
    $permissionModule = str_replace('-', '_', $module);
@endphp


@include('partials.page-header', [
    'title' => $title,
    'description' => $description
])


<style>
.master-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #e3eaf1;
}

.master-toolbar-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.master-toolbar-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-left: auto;
}

.master-entry-control {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    white-space: nowrap;
    color: #64748b;
    font-size: 14px;
}

.master-entry-control .form-select {
    width: 94px;
    height: 44px;
    min-height: 44px;
    border: 1px solid #d8e2eb;
    border-radius: 10px;
    box-shadow: none;
}

.master-entry-control .form-select:focus {
    border-color: #3f5cc4;
    box-shadow: 0 0 0 3px rgba(63, 92, 196, .08);
}

.master-search {
    position: relative;
    width: 320px;
    margin: 0;
}

.master-search i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #718096;
    pointer-events: none;
}

.master-search input {
    width: 100%;
    height: 44px;
    padding: 0 15px 0 42px;
    border: 1px solid #d8e2eb;
    border-radius: 10px;
    outline: none;
    background: #fff;
}

.master-search input:focus {
    border-color: #3f5cc4;
    box-shadow: 0 0 0 3px rgba(63, 92, 196, .08);
}

.master-search-loading {
    opacity: .65;
    pointer-events: none;
}

.master-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 15px 20px;
    border-top: 1px solid #e3eaf1;
}

.master-table-info {
    color: #64748b;
    font-size: 14px;
    white-space: nowrap;
}

.master-pagination {
    margin-left: auto;
}

.master-pagination nav,
.master-pagination .pagination {
    margin: 0;
}

.master-import-drop {
    padding: 24px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    text-align: center;
}

.master-import-drop i {
    display: block;
    margin-bottom: 10px;
    font-size: 28px;
    color: #3f5cc4;
}

@media (max-width: 992px) {
    .master-table-toolbar {
        flex-wrap: wrap;
    }

    .master-toolbar-right {
        width: 100%;
        flex-wrap: wrap;
        margin-left: 0;
    }

    .master-search {
        flex: 1;
        min-width: 240px;
    }
}

@media (max-width: 576px) {
    .master-toolbar-left,
    .master-toolbar-right {
        width: 100%;
    }

    .master-toolbar-right {
        justify-content: flex-start;
    }

    .master-search {
        width: 100%;
        min-width: 100%;
    }

    .master-table-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .master-pagination {
        width: 100%;
        margin-left: 0;
    }
}
</style>


<div class="panel">

    {{-- =====================================================
         HEADER
    ====================================================== --}}

    <div class="panel-header">

        <div>
            <h2>{{ $title }} Directory</h2>

            <p>
                {{ $records->total() }} records available
            </p>
        </div>


        @permission($permissionModule, 'create')

            <button
                class="btn btn-primary"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#createMasterModal"
            >
                <i class="fa-solid fa-plus me-2"></i>

                Add {{ $singular }}
            </button>

        @endpermission

    </div>


    {{-- =====================================================
         TOOLBAR
    ====================================================== --}}

    <div class="master-table-toolbar">

        {{-- LEFT: SHOW ENTRIES --}}

        <div class="master-toolbar-left">

            <form
                method="GET"
                action="{{ route('asset-management.'.$module.'.index') }}"
                class="master-entry-control"
            >

                @if(request('search'))

                    <input
                        type="hidden"
                        name="search"
                        value="{{ request('search') }}"
                    >

                @endif


                <span>
                    Show
                </span>


                <select
                    name="per_page"
                    class="form-select form-select-sm"
                    onchange="this.form.submit()"
                    aria-label="Rows per page"
                >

                    @foreach([10, 25, 50, 100] as $size)

                        <option
                            value="{{ $size }}"
                            @selected(
                                (int) request('per_page', 10) === $size
                            )
                        >
                            {{ $size }}
                        </option>

                    @endforeach

                </select>


                <span>
                    entries
                </span>

            </form>

        </div>


        {{-- RIGHT: SEARCH + EXPORT + IMPORT --}}

        <div class="master-toolbar-right">

            {{-- SEARCH --}}

            <form
                method="GET"
                action="{{ route('asset-management.'.$module.'.index') }}"
                class="master-search"
                id="masterSearchForm"
            >

                <i class="fa-solid fa-magnifying-glass"></i>


                <input
                    name="search"
                    id="masterSearch"
                    type="search"
                    value="{{ request('search') }}"
                    placeholder="Search {{ strtolower($title) }}..."
                    autocomplete="off"
                >


                <input
                    type="hidden"
                    name="per_page"
                    value="{{ request('per_page', 10) }}"
                >

            </form>


            {{-- CLEAR SEARCH --}}

            @if(request('search'))

                <a
                    href="{{ route('asset-management.'.$module.'.index', [
                        'per_page' => request('per_page', 10)
                    ]) }}"
                    class="btn btn-soft btn-icon"
                    title="Clear search"
                    aria-label="Clear search"
                >
                    <i class="fa-solid fa-xmark"></i>
                </a>

            @endif


            {{-- EXPORT --}}

            @permission($permissionModule, 'export')

                <a
                    href="{{ route(
                        'asset-management.'.$module.'.export',
                        request()->query()
                    ) }}"
                    class="btn btn-soft"
                >
                    <i class="fa-solid fa-file-export me-2"></i>

                    Export
                </a>

            @endpermission


            {{-- IMPORT --}}

            @permission($permissionModule, 'import')

                <button
                    type="button"
                    class="btn btn-soft"
                    data-bs-toggle="modal"
                    data-bs-target="#masterImportModal"
                >
                    <i class="fa-solid fa-file-import me-2"></i>

                    Import
                </button>

            @endpermission

        </div>

    </div>


    {{-- =====================================================
         TABLE
    ====================================================== --}}

    <div class="table-responsive">

        <table class="table data-table">

            <thead>

                <tr>

                    <th>
                        {{ $singular }}
                    </th>

                    <th>
                        Code
                    </th>


                    @if($module === 'departments')

                        <th>
                            Sub Departments
                        </th>


                    @elseif($module === 'sub-departments')

                        <th>
                            Department
                        </th>


                    @elseif($module === 'brands')

                        <th>
                            Country
                        </th>

                        <th>
                            Support Contact
                        </th>


                    @elseif($module === 'categories')

                        <th>
                            Asset Types
                        </th>

                        <th>
                            Description
                        </th>

                    @endif


                    <th>
                        Assets
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($records as $record)

                    <tr>

                        {{-- NAME --}}

                        <td>

                            <div class="cell-title">

                                @if(
                                    $module === 'brands'
                                    &&
                                    $record->logo_path
                                )

                                    <img
                                        src="{{ \App\Support\PublicUrl::storage($record->logo_path) }}"
                                        alt="{{ $record->name }} logo"
                                        style="
                                            width:36px;
                                            height:36px;
                                            object-fit:contain;
                                            border-radius:8px;
                                            background:#fff;
                                            padding:3px;
                                        "
                                    >

                                @else

                                    <span class="mini-icon">

                                        <i class="fa-solid {{ $icon }}"></i>

                                    </span>

                                @endif


                                <span>

                                    <strong>
                                        {{ $record->name }}
                                    </strong>

                                    <small>
                                        Added {{ $record->created_at->format('d M Y') }}
                                    </small>

                                </span>

                            </div>

                        </td>


                        {{-- CODE --}}

                        <td>
                            {{ $record->code }}
                        </td>


                        {{-- MODULE SPECIFIC --}}

                        @if($module === 'departments')

                            <td>
                                {{ $record->sub_departments_count }}
                            </td>


                        @elseif($module === 'sub-departments')

                            <td>
                                {{ $record->department?->name ?: '—' }}
                            </td>


                        @elseif($module === 'brands')

                            <td>
                                {{ $record->country ?: '—' }}
                            </td>

                            <td>
                                {{ $record->support_contact ?: '—' }}
                            </td>


                        @elseif($module === 'categories')

                            <td>
                                {{ $record->types_count }}
                            </td>

                            <td>
                                {{ Str::limit($record->description, 45) ?: '—' }}
                            </td>

                        @endif


                        {{-- ASSETS COUNT --}}

                        <td>
                            {{ $record->assets_count }}
                        </td>


                        {{-- STATUS --}}

                        <td>

                            <span
                                class="badge-soft {{ $record->status === 'Active' ? 'success' : 'muted' }}"
                            >
                                {{ $record->status }}
                            </span>

                        </td>


                        {{-- ACTIONS --}}

                        <td>

                            @permission($permissionModule, 'update')

                                <button
                                    class="btn btn-soft btn-icon"
                                    type="button"
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editMasterModal{{ $record->id }}"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>

                            @endpermission


                            @permission($permissionModule, 'delete')

                                <form
                                    class="d-inline"
                                    method="POST"
                                    action="{{ route(
                                        'asset-management.'.$module.'.destroy',
                                        $record->id
                                    ) }}"
                                    data-confirm
                                    data-confirm-title="Delete {{ $singular }}?"
                                    data-confirm-message="This will delete {{ $record->name }}. Records currently in use cannot be deleted."
                                    data-confirm-label="Delete {{ $singular }}"
                                >

                                    @csrf

                                    @method('DELETE')


                                    <button
                                        class="btn btn-soft btn-icon"
                                        type="submit"
                                        title="Delete"
                                    >
                                        <i class="fa-regular fa-trash-can text-danger"></i>
                                    </button>

                                </form>

                            @endpermission

                        </td>

                    </tr>


                @empty

                    <tr>

                        <td
                            colspan="10"
                            class="text-center py-5 text-secondary"
                        >

                            <i
                                class="fa-solid {{ $icon }} fa-2x mb-3 d-block"
                            ></i>

                            No {{ strtolower($title) }} found.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- =====================================================
         FOOTER
    ====================================================== --}}

    <div class="master-table-footer">

        <div class="master-table-info">

            Showing
            {{ $records->firstItem() ?? 0 }}
            to
            {{ $records->lastItem() ?? 0 }}
            of
            {{ $records->total() }}
            entries

        </div>


        <div class="master-pagination">

            @include('partials.pagination', [
                'paginator' => $records
            ])

        </div>

    </div>

</div>


{{-- =========================================================
     CREATE MODAL
========================================================= --}}

@permission($permissionModule, 'create')

    @include('asset-masters.form', [
        'record' => null
    ])

@endpermission


{{-- =========================================================
     EDIT MODALS
========================================================= --}}

@permission($permissionModule, 'update')

    @foreach($records as $record)

        @include('asset-masters.form', [
            'record' => $record
        ])

    @endforeach

@endpermission


{{-- =========================================================
     IMPORT MODAL
========================================================= --}}

@permission($permissionModule, 'import')

    <div
        class="modal fade"
        id="masterImportModal"
        tabindex="-1"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <form
                    method="POST"
                    action="{{ route(
                        'asset-management.'.$module.'.import'
                    ) }}"
                    enctype="multipart/form-data"
                >

                    @csrf


                    <div class="modal-header">

                        <div>

                            <h5 class="modal-title mb-1">
                                Import {{ $title }}
                            </h5>

                            <small class="text-secondary">
                                Upload CSV file to import records.
                            </small>

                        </div>


                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                        ></button>

                    </div>


                    <div class="modal-body">

                        <div class="master-import-drop">

                            <i class="fa-solid fa-file-csv"></i>


                            <label
                                for="masterImportFile"
                                class="form-label fw-semibold"
                            >
                                Select CSV File
                            </label>


                            <input
                                type="file"
                                name="file"
                                id="masterImportFile"
                                class="form-control"
                                accept=".csv,.txt"
                                required
                            >

                        </div>


                        <div class="alert alert-light border mt-3 mb-0">

                            <strong>
                                Expected CSV columns:
                            </strong>

                            <div class="mt-1">

                                @if($module === 'departments')

                                    name, status, description


                                @elseif($module === 'sub-departments')

                                    name, department, status, description


                                @elseif($module === 'types')

                                    name, status, description


                                @elseif($module === 'brands')

                                    name, country, support_contact, status


                                @elseif($module === 'categories')

                                    name, status, description

                                @endif

                            </div>

                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-soft"
                            data-bs-dismiss="modal"
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-file-import me-2"></i>

                            Import
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

@endpermission


@endsection


{{-- =========================================================
     LIVE SEARCH
========================================================= --}}

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('masterSearchForm');
    const search = document.getElementById('masterSearch');

    if (!form || !search) {
        return;
    }


    let timer;
    let submitting = false;


    search.addEventListener('input', function () {

        clearTimeout(timer);


        timer = setTimeout(function () {

            if (submitting) {
                return;
            }


            submitting = true;

            form.classList.add('master-search-loading');

            form.submit();

        }, 450);

    });

});
</script>

@endpush
