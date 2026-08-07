@extends('layouts.app')
@section('title','Reports')
@section('content')
@include('partials.page-header',['title'=>'Asset Reports','description'=>'Filter and fetch asset records across ownership, classification, status, warranty, and AMC coverage.'])

<div class="panel mb-3">
    <div class="panel-header">
        <div><h2>Asset Report Builder</h2><p>Choose one or more filters to fetch matching records</p></div>
        @if($hasGenerated)<a class="btn btn-soft" href="{{ route('reports.index') }}"><i class="fa-solid fa-rotate-left me-2"></i>Reset Filters</a>@endif
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('reports.generate') }}" id="assetReportForm">
            @csrf
            <div class="report-filter-section">
                <span class="report-filter-title"><i class="fa-solid fa-layer-group"></i>Classification</span>
                <div class="row g-3">
<div class="col-md-6 col-xl-3"><label class="form-label">Type</label><select class="form-select" name="asset_type_id" id="reportType"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)request('asset_type_id')===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Brand</label><select class="form-select" name="brand_id"><option value="">All brands</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)request('brand_id')===(string)$brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All statuses</option>@foreach(['Active','In Stock','Under Maintenance','Retired'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="report-filter-section">
                <span class="report-filter-title"><i class="fa-solid fa-sitemap"></i>Ownership & Assignment</span>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4"><label class="form-label">Department</label><select class="form-select" name="department_id" id="reportDepartment"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department_id')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-4"><label class="form-label">Sub Department</label><select class="form-select" name="sub_department_id" id="reportSubDepartment"><option value="">All sub departments</option>@foreach($subDepartments as $subDepartment)<option value="{{ $subDepartment->id }}" data-department="{{ $subDepartment->department_id }}" @selected((string)request('sub_department_id')===(string)$subDepartment->id)>{{ $subDepartment->name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-4"><label class="form-label">Assigned To</label><select class="form-select" name="assigned_to"><option value="">All assignments</option><option value="__unassigned__" @selected(request('assigned_to')==='__unassigned__')>Unassigned assets</option>@foreach($assignees as $assignee)<option value="{{ $assignee }}" @selected(request('assigned_to')===$assignee)>{{ $assignee }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="report-filter-section">
                <span class="report-filter-title"><i class="fa-solid fa-shield-halved"></i>Coverage</span>
                <div class="row g-3">
                    @php
                        $coverageOptions = [
                            'any' => 'Any coverage',
                            'available' => 'Active beyond 30 days',
                            'expiring' => 'Expiring within 30 days',
                            'expired' => 'Expired',
                            'none' => 'Not applicable / No coverage',
                        ];
                    @endphp
                    <div class="col-md-6"><label class="form-label">Warranty</label><select class="form-select" name="warranty"><option value="">All warranty records</option>@foreach($coverageOptions as $value=>$label)<option value="{{ $value }}" @selected(request('warranty')===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">AMC</label><select class="form-select" name="amc"><option value="">All AMC records</option>@foreach($coverageOptions as $value=>$label)<option value="{{ $value }}" @selected(request('amc')===$value)>{{ $label }}</option>@endforeach</select></div>
                </div>
            </div>

            <div class="report-form-actions">
                <span><i class="fa-solid fa-circle-info"></i> Empty filters include every value in that field.</span>
                @permission('reports','create')<button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass-chart me-2"></i>Fetch Report</button>@else<span class="badge-soft muted"><i class="fa-solid fa-lock me-1"></i>Report generation not permitted</span>@endpermission
            </div>
        </form>
    </div>
</div>

@if($hasGenerated)
<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-list-check','label'=>'Matching Assets','value'=>number_format($summary['total'])])
    @include('partials.stat-card',['icon'=>'fa-user-check','label'=>'Assigned','value'=>number_format($summary['assigned']),'class'=>'success'])
    @include('partials.stat-card',['icon'=>'fa-shield-halved','label'=>'Warranty Due','value'=>number_format($summary['warranty_due']),'class'=>'warning'])
    @include('partials.stat-card',['icon'=>'fa-screwdriver-wrench','label'=>'AMC Due','value'=>number_format($summary['amc_due']),'class'=>'danger'])
</div>

<div class="panel">
    <div class="panel-header">
        <div><h2>Filtered Asset Report</h2><p>{{ number_format($assets->total()) }} matching records</p></div>
        @permission('reports','export')<a class="btn btn-soft" href="{{ route('reports.export', request()->except(['page','generated'])) }}"><i class="fa-solid fa-file-csv me-2 text-success"></i>Export CSV</a>@endpermission
    </div>
    <div class="table-responsive">
        <table class="table data-table report-results-table">
<thead><tr><th>Asset</th><th>Type</th><th>Brand</th><th>Department</th><th>Assigned To</th><th>Status</th><th>Warranty</th><th>AMC</th></tr></thead>
            <tbody>
            @forelse($assets as $asset)
                @php
                    $coverageClass = fn ($date) => !$date ? 'muted' : ($date->isPast() ? 'danger' : ($date->lte(today()->addDays(30)) ? 'warning' : 'success'));
                @endphp
                <tr>
<td><div class="cell-title"><span class="mini-icon"><i class="fa-solid fa-laptop-file"></i></span><span><strong>{{ $asset->name }}</strong><small>{{ $asset->asset_tag }}</small></span></div></td>
                    <td>{{ $asset->type?->name ?: '—' }}</td>
                    <td>{{ $asset->brand?->name ?: '—' }}</td>
                    <td><strong>{{ $asset->department?->name ?: '—' }}</strong><small class="d-block text-secondary">{{ $asset->subDepartment?->name ?: 'No sub department' }}</small></td>
                    <td>{{ $asset->assigned_to ?: 'Unassigned' }}</td>
                    <td><span class="badge-soft {{ $asset->status==='Active'?'success':($asset->status==='Under Maintenance'?'warning':'muted') }}">{{ $asset->status }}</span></td>
                    <td><span class="badge-soft {{ $coverageClass($asset->warranty_expiry) }}">{{ $asset->warranty_expiry?->format($systemSettings?->date_format ?? 'd M Y') ?: 'Not applicable' }}</span></td>
                    <td><span class="badge-soft {{ $coverageClass($asset->amc_expiry) }}">{{ $asset->amc_expiry?->format($systemSettings?->date_format ?? 'd M Y') ?: 'Not applicable' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-report-state"><i class="fa-solid fa-filter-circle-xmark"></i><strong>No matching assets</strong><span>Change one or more filters and fetch the report again.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$assets])
</div>
@else
<div class="panel"><div class="empty-report-state report-welcome"><i class="fa-solid fa-chart-column"></i><strong>Build your asset report</strong><span>Select filters above and click Fetch Report to view live database records.</span></div></div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const connect=(parentId,childId,dataKey)=>{
  const parent=document.getElementById(parentId),child=document.getElementById(childId);
  if(!parent||!child)return;
  const sync=()=>{
   [...child.options].slice(1).forEach(option=>option.hidden=Boolean(parent.value)&&option.dataset[dataKey]!==parent.value);
   if(child.selectedOptions[0]?.hidden)child.value='';
  };
  parent.addEventListener('change',sync);sync();
 };
connect('reportDepartment','reportSubDepartment','department');
});
</script>
@endpush
