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

.master-import-info {
    padding: 16px;
    border: 1px solid #e3eaf1;
    border-radius: 12px;
    background: #f8fafc;
}

.master-import-info-label {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
}

.master-import-info-label i {
    color: #3f5cc4;
}

.master-import-columns {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.master-import-chip {
    padding: 3px 10px;
    border: 1px solid #d8e2eb;
    border-radius: 999px;
    background: #fff;
    color: #3f5cc4;
    font-size: 12.5px;
    font-family: 'SFMono-Regular', Consolas, monospace;
}

.master-import-tooltip {
    position: relative;
    display: inline-flex;
    cursor: help;
}

.master-import-tooltip-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 8px;
    width: 260px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #1f2937;
    color: #f8fafc;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.5;
    box-shadow: 0 6px 16px rgba(15, 23, 42, .18);
    z-index: 20;
}

.master-import-tooltip:hover .master-import-tooltip-content,
.master-import-tooltip:focus .master-import-tooltip-content,
.master-import-tooltip:focus-within .master-import-tooltip-content {
    display: block;
}

.master-import-tooltip-content code {
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(255, 255, 255, .15);
    color: #fff;
    font-size: 11px;
}

.master-import-download {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed #d8e2eb;
    width: 100%;
    color: #3f5cc4;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

.master-import-download i {
    font-size: 13px;
}

.master-import-download:hover {
    color: #2e4494;
    text-decoration: underline;
}

.asset-type-checklist {
    border: 1px solid #e3eaf1;
    border-radius: 12px;
    background: #f8fafc;
    padding: 14px 15px;
    margin-bottom: 16px;
}

.asset-type-checklist-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.asset-type-checklist-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #94a3b8;
}

.link-btn {
    background: none;
    border: 0;
    padding: 0;
    color: #3f5cc4;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.link-btn:hover {
    text-decoration: underline;
}

.asset-type-checklist-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin-top: 10px;
}

.asset-type-option {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    color: #334155;
    cursor: pointer;
}

.asset-type-option input {
    margin: 0;
}

.asset-type-preview {
    margin-top: 12px;
    padding-top: 11px;
    border-top: 1px dashed #d8e2eb;
}

.asset-type-preview summary {
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    color: #3f5cc4;
}

.asset-type-preview-row {
    margin-top: 10px;
}

.asset-type-preview-row strong {
    display: block;
    font-size: 12.5px;
    color: #334155;
    margin-bottom: 5px;
}

.asset-import-drop {
    padding: 22px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    text-align: center;
}

.asset-import-drop i {
    display: block;
    margin-bottom: 8px;
    font-size: 26px;
    color: #3f5cc4;
}

.asset-import-columns {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.asset-import-chip {
    padding: 3px 10px;
    border: 1px solid #d8e2eb;
    border-radius: 999px;
    background: #fff;
    color: #3f5cc4;
    font-size: 11.5px;
    font-family: 'SFMono-Regular', Consolas, monospace;
}

.asset-import-chip.dynamic {
    border-style: dashed;
    color: #0f766a;
}

.asset-import-note {
    margin: 10px 0 0;
    font-size: 12px;
    color: #64748b;
}

.params-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.params-grid:empty {
    display: none;
}

.params-grid:not(:empty) {
    margin-top: .75rem;
}

.param-field .form-label {
    font-weight: 500;
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

                @if($module === 'sub-types')

                    <button
                        type="button"
                        class="btn btn-soft"
                        data-bs-toggle="modal"
                        data-bs-target="#subtypeExportModal"
                    >
                        <i class="fa-solid fa-file-export me-2"></i>

                        Export
                    </button>

                @else

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

                @endif

            @endpermission


            {{-- IMPORT --}}

            @permission($permissionModule, 'import')

                <button
                    type="button"
                    class="btn btn-soft"
                    data-bs-toggle="modal"
                    data-bs-target="{{ $module === 'sub-types' ? '#subtypeImportModal' : '#masterImportModal' }}"
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


                    @elseif($module === 'sub-types')

                        <th>
                            Asset Type
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


                        @elseif($module === 'sub-types')

                            <td>
                                {{ $record->assetType?->name ?: '—' }}
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

                            @if(in_array($module, ['types', 'sub-types']))

                                <button
                                    class="btn btn-soft btn-icon"
                                    type="button"
                                    title="View"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewMasterModal{{ $record->id }}"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                </button>

                            @endif


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
     VIEW MODALS
========================================================= --}}

@if(in_array($module, ['types', 'sub-types']))

    @foreach($records as $record)

        @include('asset-masters.view', [
            'record' => $record
        ])

    @endforeach

@endif


{{-- =========================================================
     IMPORT MODAL
========================================================= --}}

@permission($permissionModule, 'import')

    @if($module !== 'sub-types')

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


                        @php

                            $sampleColumns = match ($module) {
                                'departments' => ['name', 'status', 'description'],
                                'sub-departments' => ['name', 'department', 'status', 'description'],
                                'types' => ['name', 'status', 'description', 'parameters'],
                                'brands' => ['name', 'country', 'support_contact', 'status'],
                                'categories' => ['name', 'status', 'description'],
                                default => [],
                            };

                            $csvEscape = fn ($value) => '"'.str_replace('"', '""', (string) $value).'"';

                            $sampleRows = match ($module) {
                                'types' => [
                                    ['Sample Laptop', 'Active', 'A sample laptop type', 'RAM|Processor|Storage'],
                                    ['Sample Desktop', 'Active', 'A sample desktop type', 'RAM|Processor|Graphics'],
                                    ['Sample Printer', 'Active', 'A sample printer type', 'Print Type|Paper Size|Connectivity'],
                                ],
                                'departments' => [
                                    ['Sample Department', 'Active', 'A sample description'],
                                    ['Sample Department 2', 'Active', 'Another sample description'],
                                ],
                                'sub-departments' => [
                                    ['Sample Sub Department', 'Sample Department', 'Active', 'A sample description'],
                                    ['Sample Sub Department 2', 'Sample Department', 'Active', 'Another sample description'],
                                ],
                                'brands' => [
                                    ['Sample Brand', 'India', 'contact@example.com', 'Active'],
                                    ['Sample Brand 2', 'USA', 'contact2@example.com', 'Active'],
                                ],
                                'categories' => [
                                    ['Sample Category', 'Active', 'A sample description'],
                                    ['Sample Category 2', 'Active', 'Another sample description'],
                                ],
                                default => [[]],
                            };

                            $sampleCsv = implode(',', array_map($csvEscape, $sampleColumns))
                                .collect($sampleRows)
                                    ->map(fn ($row) => "\n".implode(',', array_map($csvEscape, $row)))
                                    ->implode('');

                            $columnsHelp = match ($module) {
                                'types' => '<strong>Multiple parameters for one Type:</strong> separate parameter names with a pipe <code>|</code> in the <code>parameters</code> column — e.g. <code>RAM|Processor|Storage</code>. <strong>Multiple Types:</strong> add one row per Type, each with its own <code>parameters</code> list.',
                                default => 'These are the columns your import file should contain, in this order.',
                            };

                        @endphp


                        <div class="master-import-info mt-3">

                            <div class="master-import-info-label">
                                <span class="master-import-tooltip" tabindex="0">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span class="master-import-tooltip-content">{!! $columnsHelp !!}</span>
                                </span>

                                Expected CSV columns
                            </div>

                            <div class="master-import-columns">

                                @foreach($sampleColumns as $column)

                                    <span class="master-import-chip">
                                        {{ $column }}
                                    </span>

                                @endforeach

                            </div>


                            <a
                                href="data:text/csv;charset=utf-8,{{ rawurlencode($sampleCsv) }}"
                                download="{{ $module }}-sample.csv"
                                class="master-import-download"
                            >
                                <i class="fa-solid fa-download"></i>

                                Download sample format
                            </a>

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

    @endif

@endpermission


{{-- =========================================================
     SUB-TYPES: TYPE-SCOPED EXPORT / SAMPLE / IMPORT
     (one workbook sheet per Asset Type, like the Assets bulk import)
========================================================= --}}

@if($module === 'sub-types')

    @php
        $subtypeExportTypes = \App\Models\AssetType::orderBy('name')->get();
    @endphp

    @permission($permissionModule, 'export')

        <div class="modal fade" id="subtypeExportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="GET" action="{{ route('asset-management.sub-types.export') }}">
                        <div class="modal-header">
                            <h5 class="modal-title mb-1">Export Asset Subtypes</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-secondary small">Pick the Asset Types to export — all are selected by default. The workbook will have one sheet per Type, with only that Type's own configured parameters as columns.</p>
                            @include('partials.asset-type-checklist', ['types' => $subtypeExportTypes, 'prefix' => 'subtype-export', 'csvClass' => \App\Support\SubtypeCsv::class])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-export me-2"></i>Export</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endpermission

    @permission($permissionModule, 'import')

        <div class="modal fade" id="subtypeSampleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="GET" action="{{ route('asset-management.sub-types.import-sample') }}">
                        <div class="modal-header">
                            <h5 class="modal-title mb-1">Download Import Sample</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-secondary small">Pick the Asset Types you want a sheet for — all are selected by default ("All"). Each sheet's columns come straight from that Type's current Parameters.</p>
                            @include('partials.asset-type-checklist', ['types' => $subtypeExportTypes, 'prefix' => 'subtype-sample', 'csvClass' => \App\Support\SubtypeCsv::class])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="subtypeImportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('asset-management.sub-types.import') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title mb-1">Import Asset Subtypes</h5>
                                <small class="text-secondary">Upload an Excel file — one sheet per Asset Type.</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <a class="btn btn-soft btn-sm" href="#" data-bs-toggle="modal" data-bs-target="#subtypeSampleModal">
                                    <i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample (choose types)
                                </a>
                            </div>

                            <div class="master-import-drop">
                                <i class="fa-solid fa-file-excel"></i>
                                <label for="subtypeImportFile" class="form-label fw-semibold mb-2">Excel file (.xlsx / .xls)</label>
                                <input class="form-control" id="subtypeImportFile" type="file" name="file" accept=".xlsx,.xls" required>
                            </div>

                            <small class="text-secondary d-block mt-2">
                                Each sheet's <strong>name must be an Asset Type</strong> (e.g. "Laptop", "Desktop") — rows are matched to that Type's own configured parameters. Leave Code blank to create a new Subtype; enter an existing Subtype Code to update it instead.
                            </small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-2"></i>Import</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    @endpermission

@endif


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


{{-- =========================================================
     TYPE SUBTYPE PARAMETERS (dynamic "+ Add Parameter" rows)
========================================================= --}}

@if($module === 'types')

    @push('scripts')

    <script>
    document.addEventListener('click', function (e) {

        const addButton = e.target.closest('[data-add-parameter]');

        if (addButton) {
            const section = addButton.closest('[data-parameters-section]');
            const list = section?.querySelector('[data-parameters-list]');

            if (!list) return;

            const row = document.createElement('div');
            row.className = 'input-group mb-2';
            row.setAttribute('data-parameter-row', '');
            row.innerHTML = '<input class="form-control" name="parameters[]" placeholder="Parameter name (e.g. RAM)">'
                + '<button type="button" class="btn btn-outline-danger" data-remove-parameter><i class="fa-solid fa-xmark"></i></button>';

            list.appendChild(row);
            row.querySelector('input')?.focus();

            return;
        }

        const removeButton = e.target.closest('[data-remove-parameter]');

        if (removeButton) {
            removeButton.closest('[data-parameter-row]')?.remove();
        }

    });
    </script>

    @endpush

@endif


{{-- =========================================================
     SUBTYPE PARAMETER VALUES (loaded from the selected Type)
========================================================= --}}

@if($module === 'sub-types')

    @push('scripts')

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const typeParameters = @json($options['type_parameters'] ?? []);

        const renderParameterValues = (select) => {
            const scope = select.closest('form');
            const section = scope?.querySelector('[data-subtype-parameters-section]');
            const list = section?.querySelector('[data-subtype-parameters-list]');
            const empty = section?.querySelector('[data-subtype-parameters-empty]');

            if (!list) return;

            let existing = {};
            try {
                existing = JSON.parse(section.dataset.existing || '{}') || {};
            } catch (e) {
                existing = {};
            }

            const params = typeParameters[select.value] || [];

            list.innerHTML = '';

            if (empty) empty.hidden = params.length > 0;

            params.forEach((param) => {
                const col = document.createElement('div');
                col.className = 'param-field';

                const label = document.createElement('label');
                label.className = 'form-label mb-1';
                label.textContent = param;

                const input = document.createElement('input');
                input.className = 'form-control';
                input.name = 'parameter_values[' + param + ']';
                input.value = existing[param] ?? '';

                col.append(label, input);
                list.append(col);
            });
        };

        document.querySelectorAll('[data-subtype-type-select]').forEach((select) => {
            select.addEventListener('change', () => renderParameterValues(select));
            renderParameterValues(select);
        });

    });
    </script>

    @endpush

@endif
