@extends('layouts.app')
@section('title','UPS Monitoring')
@section('content')
@include('partials.page-header',['title'=>'UPS & Power Systems','description'=>'Track load, battery health, runtime, and preventive maintenance.','actionUrl'=>'#','actionLabel'=>'Add UPS'])
<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">@include('partials.stat-card',['icon'=>'fa-battery-full','label'=>'Total UPS','value'=>'64']) @include('partials.stat-card',['icon'=>'fa-bolt','label'=>'Online','value'=>'61','class'=>'success']) @include('partials.stat-card',['icon'=>'fa-triangle-exclamation','label'=>'Needs Attention','value'=>'3','class'=>'danger']) @include('partials.stat-card',['icon'=>'fa-car-battery','label'=>'Avg Battery Health','value'=>'87%','class'=>'info'])</div>
<div class="row g-3">@foreach([['UPS-DC-01','APC Smart-UPS SRT 10kVA','Primary Datacenter','96','42','74 min','14 Aug 2026'],['UPS-MUM-F04','Vertiv Liebert GXT5','Mumbai · Floor 4','82','61','38 min','02 Jul 2026'],['UPS-DEL-F02','APC Smart-UPS 3000VA','Delhi · Floor 2','48','38','22 min','28 Jun 2026'],['UPS-BLR-SRV','Eaton 9PX 6000i','Bengaluru Server Room','73','54','46 min','18 Sep 2026']] as $r)<div class="col-md-6">
        <div class="panel">
            <div class="panel-header">
                <div>   
                    <h2>{{ $r[0] }}</h2>
                    <p>{{ $r[1] }}</p>
                </div><span class="badge-soft {{ $r[3]<60?'danger':'success' }}">Online</span>
            </div>
            <div class="panel-body">
                <div class="d-flex justify-content-between mb-3"><span class="small text-secondary"><i class="fa-solid fa-location-dot me-1"></i>{{ $r[2] }}</span><strong>{{ $r[5] }} runtime</strong></div>
                <p class="small mb-1">Battery health <span class="float-end">{{ $r[3] }}%</span></p>
                <div class="progress mb-3">
                    <div class="progress-bar {{ $r[3]<60?'bg-danger':'bg-success' }}" style="width:{{ $r[3] }}%"></div>
                </div>
                <p class="small mb-1">Current load <span class="float-end">{{ $r[4] }}%</span></p>
                <div class="progress mb-3">
                    <div class="progress-bar bg-info" style="width:{{ $r[4] }}%"></div>
                </div><small class="text-secondary">Next maintenance: {{ $r[6] }}</small>
            </div>
        </div>
    </div>@endforeach</div>
@endsection