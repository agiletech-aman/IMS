@extends('layouts.app')
@section('title','Dashboard')
@section('content')
@php
    $signedInUser = session('static_auth_user', ['name' => 'User']);
    $firstName = Str::before($signedInUser['name'], ' ');
@endphp

<div class="dashboard-heading">
    <div>
        <p class="eyebrow" id="dashboardGreeting" data-name="{{ $firstName }}">Welcome back, {{ $firstName }}</p>
        <h1>Dashboard</h1>
        <p>Live overview of assets, users, access accounts, vendors, alerts, coverage, and system activity.</p>
    </div>
    <div class="dashboard-filter">
        <div class="period-switch" role="group" aria-label="Dashboard activity period">
            @foreach(['day'=>'Day','week'=>'Week','month'=>'Month'] as $value=>$label)
                <a class="period-btn {{ $period === $value ? 'active' : '' }}" href="{{ route('dashboard',['period'=>$value]) }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="dropdown">
            <button class="date-range-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="fa-regular fa-calendar"></i>
                <span>{{ $from->format('d M Y') }} - {{ $to->format('d M Y') }}</span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end date-range-menu">
                <form method="GET" action="{{ route('dashboard') }}">
                    <label class="form-label">Custom activity range</label>
                    <div class="date-inputs">
                        <div><small>From</small><input class="form-control" name="from" type="date" value="{{ request('from',$from->format('Y-m-d')) }}" required></div>
                        <span>—</span>
                        <div><small>To</small><input class="form-control" name="to" type="date" value="{{ request('to',$to->format('Y-m-d')) }}" required></div>
                    </div>
                    <button class="btn btn-primary w-100 mt-3"><i class="fa-solid fa-filter me-2"></i>Apply Range</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
    @foreach($stats as $stat)
        @include('partials.stat-card',[
            'icon'=>$stat['icon'],
            'label'=>$stat['label'],
            'value'=>number_format($stat['value']),
            'class'=>$stat['class'],
        ])
    @endforeach
</div>

@if($visibility['assets'])<div class="row g-3 mb-3">
    <div class="col-xl-5">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Asset Distribution</h2><p>Live inventory grouped by type</p></div><span class="badge-soft">{{ number_format($assetTotal) }} total</span></div>
            <div class="panel-body"><div class="chart-wrap"><canvas id="assetCategoryChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Asset Status Overview</h2><p>Current operational state across inventory</p></div><a href="{{ route('assets.index') }}" class="small">Manage assets</a></div>
            <div class="panel-body"><div class="chart-wrap"><canvas id="assetStatusChart"></canvas></div></div>
        </div>
    </div>
</div>@endif

@if($visibility['audit'] || ($visibility['reports'] && $visibility['assets']))<div class="row g-3 mb-3">
    @if($visibility['audit'])
    <div class="col-xl-6">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>System Activity</h2><p>Audit actions within the selected period</p></div><span class="badge-soft info">{{ number_format($activityTotal) }} events</span></div>
            <div class="panel-body"><div class="chart-wrap"><canvas id="activityChart"></canvas></div></div>
        </div>
    </div>
    @endif
    @if($visibility['reports'] && $visibility['assets'])
    <div class="col-xl-6">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Coverage Forecast</h2><p>Warranty and AMC deadlines over the next 90 days</p></div><a href="{{ route('reports.index') }}" class="small">Open reports</a></div>
            <div class="panel-body"><div class="chart-wrap"><canvas id="coverageChart"></canvas></div></div>
        </div>
    </div>
    @endif
</div>@endif

@if($visibility['assets'] || $visibility['notifications'] || $visibility['audit'])<div class="row g-3 mb-3">
    @if($visibility['assets'])
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Recent Assets</h2><p>Latest inventory records</p></div><a href="{{ route('assets.index') }}" class="small">View all</a></div>
            <div class="panel-body">
                <ul class="list-widget">
                    @forelse($recentAssets as $asset)
                        <li><div><strong>{{ $asset->name }}</strong><small>{{ $asset->asset_tag }} · {{ $asset->type?->name ?: 'No type' }}</small></div><span class="badge-soft {{ $asset->status === 'Active' ? 'success' : ($asset->status === 'Under Maintenance' ? 'warning' : 'muted') }}">{{ $asset->status }}</span></li>
                    @empty
                        <li class="dashboard-empty"><i class="fa-solid fa-box-open"></i><span>No assets available</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
    @if($visibility['notifications'])
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Notifications & Alerts</h2><p>Latest in-app alerts</p></div><a href="{{ route('notifications.index') }}" class="small">View all</a></div>
            <div class="panel-body">
                <ul class="list-widget">
                    @forelse($recentAlerts as $alert)
                        <li><div><strong>{{ Str::limit($alert->title,42) }}</strong><small>{{ $alert->module }} · {{ $alert->created_at->diffForHumans() }}</small></div><span class="badge-soft {{ in_array($alert->severity,['critical','danger']) ? 'danger' : ($alert->severity === 'warning' ? 'warning' : ($alert->severity === 'success' ? 'success' : '')) }}">{{ ucfirst($alert->severity) }}</span></li>
                    @empty
                        <li class="dashboard-empty"><i class="fa-regular fa-bell"></i><span>No alerts generated yet</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
    @if($visibility['audit'])
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Audit Activity</h2><p>Latest recorded actions</p></div><a href="{{ route('audit-logs.index') }}" class="small">Audit log</a></div>
            <div class="panel-body">
                <ul class="timeline">
                    @forelse($recentActivity as $activity)
                        <li><span class="timeline-dot"></span><div><p><strong>{{ $activity->action }}</strong> · {{ Str::limit($activity->description,52) }}</p><small>{{ $activity->actor_name }} · {{ $activity->created_at->diffForHumans() }}</small></div></li>
                    @empty
                        <li class="dashboard-empty"><i class="fa-solid fa-clock-rotate-left"></i><span>No audit activity yet</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
</div>@endif

@if(($visibility['reports'] && $visibility['assets']) || $visibility['vendors'])<div class="row g-3">
    @if($visibility['reports'] && $visibility['assets'])
    <div class="col-xl-6">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Upcoming AMC & Warranty</h2><p>Coverage expiring within 60 days</p></div><a href="{{ route('reports.index',['warranty'=>'expiring','generated'=>1]) }}" class="small">Coverage report</a></div>
            <div class="panel-body">
                <ul class="list-widget">
                    @forelse($upcomingExpiries as $expiry)
                        <li><div><strong>{{ $expiry['asset']->name }}</strong><small>{{ $expiry['asset']->asset_tag }} · {{ $expiry['type'] }} · {{ $expiry['date']->format('d M Y') }}</small></div><span class="badge-soft {{ $expiry['days'] <= 7 ? 'danger' : 'warning' }}">{{ round($expiry['days']) }} days</span></li>
                    @empty
                        <li class="dashboard-empty"><i class="fa-solid fa-shield-halved"></i><span>No coverage expires within 60 days</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
    @if($visibility['vendors'])
    <div class="col-xl-6">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Vendor / OEM Renewals</h2><p>Contracts requiring attention</p></div><a href="{{ route('vendors.index') }}" class="small">Manage vendors</a></div>
            <div class="panel-body">
                <ul class="list-widget">
                    @forelse($vendorRenewals as $vendor)
                        <li><div><strong>{{ $vendor->name }}</strong><small>{{ $vendor->vendor_type }} · {{ $vendor->contract_end?->format('d M Y') ?: 'No contract end date' }}</small></div><span class="badge-soft {{ $vendor->amc_status === 'Expired' ? 'danger' : 'warning' }}">{{ $vendor->amc_status }}</span></li>
                    @empty
                        <li class="dashboard-empty"><i class="fa-solid fa-handshake"></i><span>No vendor renewals require attention</span></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @endif
</div>@endif

<style>
.period-switch .period-btn{display:grid;place-items:center;text-decoration:none}
.dashboard-empty{min-height:72px!important;justify-content:center!important;gap:9px!important;color:var(--text-muted);border:0!important}
.dashboard-empty i{color:var(--accent-color);font-size:18px}
</style>
@endsection

@push('scripts')
<script>
(()=>{
 const chartsData={
  categories:{labels:@json($categoryChart['labels']),values:@json($categoryChart['values'])},
  statuses:{labels:@json($statusChart['labels']),values:@json($statusChart['values'])},
  activity:{labels:@json($activityChart['labels']),values:@json($activityChart['values'])},
  coverage:{labels:@json($coverageChart['labels']),warranty:@json($coverageChart['warranty']),amc:@json($coverageChart['amc'])}
 };
 const greetingNode=document.getElementById('dashboardGreeting');
 const hour=new Date().getHours();
 const greeting=hour<12?'Good morning':hour<17?'Good afternoon':'Good evening';
 greetingNode.textContent=`${greeting}, ${greetingNode.dataset.name} 👋`;
 let charts=[];
 const palette=['#3152ac','#0abfbd','#6b7ed2','#f59e0b','#10b981','#ef4444','#64748b','#38bdf8'];
 const dataOrEmpty=(labels,values)=>values.some(value=>Number(value)>0)?{labels,values}:{labels:['No data'],values:[1]};
 const drawCharts=()=>{
  const dark=document.documentElement.dataset.theme==='dark',grid=dark?'#334155':'#e2e8f0',text=dark?'#94a3b8':'#64748b';
  charts.forEach(chart=>chart.destroy());Chart.defaults.color=text;
  const common={responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{usePointStyle:true,boxWidth:7}}}};
  const category=dataOrEmpty(chartsData.categories.labels,chartsData.categories.values);
  const activity=dataOrEmpty(chartsData.activity.labels,chartsData.activity.values);
  charts=[];
  const categoryCanvas=document.getElementById('assetCategoryChart');
  const statusCanvas=document.getElementById('assetStatusChart');
  const activityCanvas=document.getElementById('activityChart');
  const coverageCanvas=document.getElementById('coverageChart');
  if(categoryCanvas)charts.push(new Chart(categoryCanvas,{type:'doughnut',data:{labels:category.labels,datasets:[{data:category.values,backgroundColor:category.labels[0]==='No data'?['#cbd5e1']:palette,borderWidth:0}]},options:{...common,cutout:'70%'}}));
  if(statusCanvas)charts.push(new Chart(statusCanvas,{type:'bar',data:{labels:chartsData.statuses.labels,datasets:[{label:'Assets',data:chartsData.statuses.values,backgroundColor:['#10b981','#38bdf8','#f59e0b','#64748b'],borderRadius:7}]},options:{...common,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}}}}));
  if(activityCanvas)charts.push(new Chart(activityCanvas,{type:'doughnut',data:{labels:activity.labels,datasets:[{data:activity.values,backgroundColor:activity.labels[0]==='No data'?['#cbd5e1']:palette,borderWidth:0}]},options:{...common,cutout:'66%'}}));
  if(coverageCanvas)charts.push(new Chart(coverageCanvas,{type:'bar',data:{labels:chartsData.coverage.labels,datasets:[{label:'Warranty',data:chartsData.coverage.warranty,backgroundColor:'#0abfbd',borderRadius:6},{label:'AMC',data:chartsData.coverage.amc,backgroundColor:'#3152ac',borderRadius:6}]},options:{...common,scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{precision:0},grid:{color:grid}}}}}));
 };
 drawCharts();
 window.addEventListener('themechange',drawCharts);
})();
</script>
@endpush
