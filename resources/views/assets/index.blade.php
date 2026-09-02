@extends('layouts.app')

@section('title','Assets')

@section('content')

@include('partials.page-header',[
    'title'=>'Asset Management',
    'description'=>'Track the complete lifecycle of every enterprise IT asset.'
] + (
    app(\App\Services\PermissionService::class)->allows('assets','create')
        ? [
            'actionUrl'=>route('assets.create'),
            'actionLabel'=>'Add Asset'
        ]
        : []
))

<style>
.asset-filter-bar {
    display: grid;
    grid-template-columns:
        minmax(280px, 1.6fr)
        repeat(3, minmax(150px, .7fr))
        repeat(2, minmax(145px, .65fr))
        auto;
    gap: 10px;
    padding: 18px 20px;
    align-items: center;
    border-bottom: 1px solid var(--border-color, #e3eaf1);
}

.asset-search-box {
    position: relative;
}

.asset-search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #718096;
    pointer-events: none;
}

.asset-search-box input {
    width: 100%;
    height: 44px;
    border: 1px solid #d8e2eb;
    border-radius: 10px;
    padding: 0 15px 0 42px;
    outline: none;
    background: #fff;
}

.asset-search-box input:focus {
    border-color: #3f5cc4;
    box-shadow: 0 0 0 3px rgba(63, 92, 196, .08);
}

.asset-filter-bar .form-select,
.asset-filter-bar .form-control {
    min-height: 44px;
    border-radius: 10px;
    border-color: #d8e2eb;
}

.asset-filter-bar .form-select:focus,
.asset-filter-bar .form-control:focus {
    border-color: #3f5cc4;
    box-shadow: 0 0 0 3px rgba(63, 92, 196, .08);
}

.asset-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 20px;
}

.asset-data-table thead th a {
    color: inherit;
    text-decoration: none;
}

.asset-data-table thead th a:hover {
    color: var(--bs-primary);
}

.asset-list-image {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 9px;
}

.asset-clear-filter {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.asset-filter-bar.loading {
    opacity: .7;
    pointer-events: none;
}


/* =========================================================
   DATATABLE STYLE FOOTER
========================================================= */

.asset-table-footer {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 16px 20px;
    border-top: 1px solid #e3eaf1;
    background: #fff;
}

.asset-entry-control {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 14px;
    color: #64748b;
    white-space: nowrap;
}

.asset-entry-control .form-select {
    width: 76px;
    min-height: 36px;
    height: 36px;
    padding-top: 4px;
    padding-bottom: 4px;
    border-radius: 8px;
    border-color: #d8e2eb;
    font-size: 14px;
}

.asset-entry-control .form-select:focus {
    border-color: #3f5cc4;
    box-shadow: 0 0 0 3px rgba(63, 92, 196, .08);
}

.asset-table-info {
    font-size: 14px;
    color: #64748b;
    white-space: nowrap;
}

.asset-pagination {
    margin-left: auto;
}

.asset-pagination nav {
    margin: 0;
}

.asset-pagination .pagination {
    margin: 0;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1350px) {
    .asset-filter-bar {
        grid-template-columns: repeat(4, 1fr);
    }

    .asset-search-box {
        grid-column: span 2;
    }
}

@media (max-width: 992px) {
    .asset-filter-bar {
        grid-template-columns: repeat(2, 1fr);
    }

    .asset-search-box {
        grid-column: 1 / -1;
    }

    .asset-table-footer {
        flex-wrap: wrap;
    }

    .asset-pagination {
        width: 100%;
        margin-left: 0;
    }
}

@media (max-width: 576px) {
    .asset-filter-bar {
        grid-template-columns: 1fr;
    }

    .asset-search-box {
        grid-column: auto;
    }

    .asset-table-footer {
        flex-direction: column;
        align-items: flex-start;
    }

    .asset-pagination {
        width: 100%;
    }
}
</style>


{{-- =========================================================
     STAT CARDS
========================================================= --}}

<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">

    @include('partials.stat-card',[
        'icon'=>'fa-cubes',
        'label'=>'Total Assets',
        'value'=>number_format($stats['total'])
    ])

    @include('partials.stat-card',[
        'icon'=>'fa-circle-check',
        'label'=>'Assigned',
        'value'=>number_format($stats['assigned']),
        'class'=>'success'
    ])

    @include('partials.stat-card',[
        'icon'=>'fa-box-open',
        'label'=>'In Stock',
        'value'=>number_format($stats['stock']),
        'class'=>'info'
    ])

    @include('partials.stat-card',[
        'icon'=>'fa-screwdriver-wrench',
        'label'=>'Maintenance',
        'value'=>number_format($stats['maintenance']),
        'class'=>'warning'
    ])

</div>


<div class="panel">

    {{-- =====================================================
         FILTER BAR
    ====================================================== --}}

    <form
        method="GET"
        action="{{ route('assets.index') }}"
        class="asset-filter-bar"
        id="assetFilterForm"
    >

        <div class="asset-search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="search"
                name="search"
                id="assetSearch"
                value="{{ request('search') }}"
                placeholder="Search asset ID, name, serial, FR number..."
                autocomplete="off"
            >

        </div>


        {{-- ASSET TYPE --}}

        <select
            name="type"
            class="form-select auto-filter"
            aria-label="Filter by asset type"
        >

            <option value="">
                All Types
            </option>

            @foreach($assetTypes as $type)

                <option
                    value="{{ $type->id }}"
                    @selected(request('type') == $type->id)
                >
                    {{ $type->name }}
                </option>

            @endforeach

        </select>


        {{-- DEPARTMENT --}}

        <select
            name="department"
            class="form-select auto-filter"
            aria-label="Filter by department"
        >

            <option value="">
                All Departments
            </option>

            @foreach($departments as $department)

                <option
                    value="{{ $department->id }}"
                    @selected(request('department') == $department->id)
                >
                    {{ $department->name }}
                </option>

            @endforeach

        </select>


        {{-- STATUS --}}

        <select
            name="status"
            class="form-select auto-filter"
            aria-label="Filter by status"
        >

            <option value="">
                All Status
            </option>

            <option
                value="Active"
                @selected(request('status') === 'Active')
            >
                Active
            </option>

            <option
                value="In Stock"
                @selected(request('status') === 'In Stock')
            >
                In Stock
            </option>

            <option
                value="Under Maintenance"
                @selected(request('status') === 'Under Maintenance')
            >
                Under Maintenance
            </option>

            <option
                value="Retired"
                @selected(request('status') === 'Retired')
            >
                Retired
            </option>

        </select>


        {{-- DATE FROM --}}

        <input
            type="date"
            name="date_from"
            class="form-control auto-filter"
            value="{{ request('date_from') }}"
            title="Installation date from"
            aria-label="Installation date from"
        >


        {{-- DATE TO --}}

        <input
            type="date"
            name="date_to"
            class="form-control auto-filter"
            value="{{ request('date_to') }}"
            title="Installation date to"
            aria-label="Installation date to"
        >


        {{-- CLEAR FILTERS --}}

        @if(
            request('search') ||
            request('type') ||
            request('department') ||
            request('status') ||
            request('date_from') ||
            request('date_to') ||
            request('assigned') ||
            request('coverage_due')
        )

            <a
                href="{{ route('assets.index') }}"
                class="btn btn-soft asset-clear-filter"
                title="Clear filters"
                aria-label="Clear filters"
            >
                <i class="fa-solid fa-rotate-left"></i>
            </a>

        @endif

    </form>


    {{-- =====================================================
         TABLE ACTIONS
    ====================================================== --}}

<div class="table-toolbar asset-table-toolbar">

    {{-- SHOW ENTRIES LEFT --}}
    <form
        method="GET"
        action="{{ route('assets.index') }}"
        id="perPageForm"
        class="asset-entry-control"
    >

        @foreach(request()->except('per_page', 'page') as $key => $value)

            @if(is_array($value))

                @foreach($value as $subKey => $subValue)
                    <input
                        type="hidden"
                        name="{{ $key }}[{{ $subKey }}]"
                        value="{{ $subValue }}"
                    >
                @endforeach

            @else

                <input
                    type="hidden"
                    name="{{ $key }}"
                    value="{{ $value }}"
                >

            @endif

        @endforeach


        <span>Show</span>

        <select
            name="per_page"
            class="form-select form-select-sm"
            onchange="this.form.submit()"
            aria-label="Rows per page"
        >
            @foreach([10,25,50,100] as $size)

                <option
                    value="{{ $size }}"
                    @selected((int) request('per_page', 10) === $size)
                >
                    {{ $size }}
                </option>

            @endforeach
        </select>

        <span>entries</span>

    </form>


    {{-- EXPORT / IMPORT RIGHT --}}
    <div class="table-actions">

        @permission('assets','export')
            <button
                type="button"
                class="btn btn-soft"
                data-bs-toggle="modal"
                data-bs-target="#assetExportModal"
            >
                <i class="fa-solid fa-file-export me-2"></i>
                Export
            </button>
        @endpermission


        @permission('assets','import')
            <button
                type="button"
                class="btn btn-soft"
                data-bs-toggle="modal"
                data-bs-target="#assetImportModal"
            >
                <i class="fa-solid fa-file-import me-2"></i>
                Import
            </button>
        @endpermission

    </div>

</div>


    {{-- =====================================================
         ASSETS TABLE
    ====================================================== --}}

    <div class="table-responsive">

        <table class="table asset-data-table">

            <thead>

                <tr>

                    {{-- ASSET --}}

                    <th>

                        <a
                            href="{{ request()->fullUrlWithQuery([
                                'sort' => 'name',
                                'direction' =>
                                    request('sort') === 'name' &&
                                    request('direction') === 'asc'
                                        ? 'desc'
                                        : 'asc'
                            ]) }}"
                        >

                            Asset

                            @if(request('sort') === 'name')

                                <i
                                    class="fa-solid fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }}"
                                ></i>

                            @endif

                        </a>

                    </th>


                    {{-- TYPE --}}

                    <th>
                        Type
                    </th>


                    {{-- SERIAL NUMBER --}}

                    <th>

                        <a
                            href="{{ request()->fullUrlWithQuery([
                                'sort' => 'serial_number',
                                'direction' =>
                                    request('sort') === 'serial_number' &&
                                    request('direction') === 'asc'
                                        ? 'desc'
                                        : 'asc'
                            ]) }}"
                        >

                            Serial Number

                            @if(request('sort') === 'serial_number')

                                <i
                                    class="fa-solid fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }}"
                                ></i>

                            @endif

                        </a>

                    </th>


                    {{-- FR NUMBER --}}

                    <th>
                        FR Number
                    </th>


                    {{-- DEPARTMENT --}}

                    <th>
                        Department
                    </th>


                    {{-- INSTALLATION DATE --}}

                    <th>

                        <a
                            href="{{ request()->fullUrlWithQuery([
                                'sort' => 'installation_date',
                                'direction' =>
                                    request('sort') === 'installation_date' &&
                                    request('direction') === 'asc'
                                        ? 'desc'
                                        : 'asc'
                            ]) }}"
                        >

                            Installation Date

                            @if(request('sort') === 'installation_date')

                                <i
                                    class="fa-solid fa-sort-{{ request('direction') === 'asc' ? 'up' : 'down' }}"
                                ></i>

                            @endif

                        </a>

                    </th>


                    {{-- STATUS --}}

                    <th>
                        Status
                    </th>


                    {{-- ACTIONS --}}

                    <th class="text-end">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($assets as $asset)

                    <tr>

                        {{-- ASSET --}}

                        <td>

                            <div class="cell-title">

                                @if($asset->image_path)

                                    <img
                                        src="{{ \App\Support\PublicUrl::storage($asset->image_path) }}"
                                        alt="{{ $asset->name }}"
                                        class="asset-list-image"
                                    >

                                @else

                                    <span class="mini-icon">
                                        <i class="fa-solid fa-laptop"></i>
                                    </span>

                                @endif


                                <span>

                                    <strong>
                                        {{ $asset->name }}
                                    </strong>

                                    <small>
                                        #{{ $asset->asset_tag }}
                                    </small>

                                </span>

                            </div>

                        </td>


                        {{-- TYPE --}}

                        <td>
                            {{ $asset->type?->name ?: '—' }}
                        </td>


                        {{-- SERIAL NUMBER --}}

                        <td>
                            {{ $asset->serial_number ?: '—' }}
                        </td>


                        {{-- FR NUMBER --}}

                        <td>
                            {{ $asset->fr_number ?: '—' }}
                        </td>


                        {{-- DEPARTMENT --}}

                        <td>
                            {{ $asset->department?->name ?: '—' }}
                        </td>


                        {{-- INSTALLATION DATE --}}

                        <td>
                            {{ $asset->installation_date?->format('d M Y') ?? '—' }}
                        </td>


                        {{-- STATUS --}}

                        <td>

                            @php

                                $statusClass = match($asset->status) {
                                    'Active' => 'success',
                                    'Under Maintenance' => 'warning',
                                    'In Stock' => 'info',
                                    'Retired' => 'danger',
                                    default => ''
                                };

                            @endphp

                            <span class="badge-soft {{ $statusClass }}">
                                {{ $asset->status }}
                            </span>

                        </td>


                        {{-- ACTIONS --}}

                        <td class="text-end text-nowrap">

                            {{-- VIEW --}}

                            <a
                                class="btn btn-soft btn-icon"
                                href="{{ route('assets.show',$asset) }}"
                                title="View asset"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </a>


                            {{-- EDIT --}}

                            @permission('assets','update')

                                <a
                                    class="btn btn-soft btn-icon"
                                    href="{{ route('assets.edit',$asset) }}"
                                    title="Edit asset"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </a>

                            @endpermission


                            {{-- DELETE --}}

                            @permission('assets','delete')

                                <form
                                    class="d-inline"
                                    method="POST"
                                    action="{{ route('assets.destroy',$asset) }}"
                                    data-confirm
                                    data-confirm-title="Delete Asset?"
                                    data-confirm-message="This will permanently delete {{ $asset->name }} ({{ $asset->asset_tag }}). This action cannot be undone."
                                    data-confirm-label="Delete Asset"
                                >

                                    @csrf

                                    @method('DELETE')


                                    <button
                                        class="btn btn-soft btn-icon"
                                        type="submit"
                                        title="Delete asset"
                                        aria-label="Delete {{ $asset->name }}"
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
                            colspan="8"
                            class="text-center py-5 text-secondary"
                        >

                            <i
                                class="fa-solid fa-box-open fa-2x mb-3 d-block"
                            ></i>

                            No assets match the selected filters.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


{{-- =====================================================
     DATATABLE STYLE FOOTER
====================================================== --}}

<div class="asset-table-footer">

    <div class="asset-table-info">
        Showing
        {{ $assets->firstItem() ?? 0 }}
        to
        {{ $assets->lastItem() ?? 0 }}
        of
        {{ $assets->total() }}
        entries
    </div>


    <div class="asset-pagination">
        @include('partials.pagination',[
            'paginator'=>$assets
        ])
    </div>

</div>

</div>


{{-- =========================================================
     IMPORT / EXPORT MODALS
========================================================= --}}

@if(app(\App\Services\PermissionService::class)->allows('assets','import') || app(\App\Services\PermissionService::class)->allows('assets','export'))

    @include('partials.asset-import-modal')

@endif


@endsection


{{-- =========================================================
     AUTO FILTER SCRIPT
========================================================= --}}

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('assetFilterForm');
    const search = document.getElementById('assetSearch');

    if (!form) {
        return;
    }

    let submitting = false;


    function submitFilters() {

        if (submitting) {
            return;
        }

        submitting = true;

        form.classList.add('loading');

        form.submit();
    }


    /*
    |--------------------------------------------------------------------------
    | Dropdown + Date Filters
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.auto-filter').forEach(function (element) {

        element.addEventListener('change', function () {

            submitFilters();

        });

    });


    /*
    |--------------------------------------------------------------------------
    | Search Debounce
    |--------------------------------------------------------------------------
    */

    if (search) {

        let searchTimer;

        search.addEventListener('input', function () {

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {

                submitFilters();

            }, 500);

        });

    }

});
</script>

@endpush


{{-- =========================================================
     IMPORT MODAL AUTO OPEN
========================================================= --}}

@if(
    app(\App\Services\PermissionService::class)->allows('assets','import')
    &&
    (
        session('openAssetImportModal')
        ||
        $errors->assetImport->any()
    )
)

    @push('scripts')

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const modal = document.getElementById('assetImportModal');

        if (modal) {

            bootstrap.Modal
                .getOrCreateInstance(modal)
                .show();

        }

    });
    </script>

    @endpush

@endif