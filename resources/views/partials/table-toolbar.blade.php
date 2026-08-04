<div class="table-toolbar">
    <div class="table-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ $placeholder ?? 'Search records…' }}"></div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-soft"><i class="fa-solid fa-filter me-2"></i>Filters</button>
        @if($export ?? true)<button class="btn btn-soft"><i class="fa-solid fa-download me-2"></i>Export</button>@endif
    </div>
</div>
