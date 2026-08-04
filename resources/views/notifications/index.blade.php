@extends('layouts.app')
@section('title','Notifications & Alerts')
@section('content')
@php
    $notificationPermissionService = app(\App\Services\PermissionService::class);
    $canManageAlertRules = $notificationPermissionService->allows('settings')
        && $notificationPermissionService->allows('settings_advanced', 'update');
@endphp
@include('partials.page-header',['title'=>'Notifications & Alerts','description'=>'Monitor system events and control in-app and email delivery.'] + ($canManageAlertRules ? ['actionUrl'=>'#alertRules','actionLabel'=>'Manage Alert Rules','actionIcon'=>'fa-sliders'] : []))

<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-bell','label'=>'Unread','value'=>number_format($stats['unread']),'class'=>'info'])
    @include('partials.stat-card',['icon'=>'fa-circle-exclamation','label'=>'Critical Alerts','value'=>number_format($stats['critical']),'class'=>'danger'])
    @include('partials.stat-card',['icon'=>'fa-triangle-exclamation','label'=>'Warnings','value'=>number_format($stats['warnings']),'class'=>'warning'])
    @include('partials.stat-card',['icon'=>'fa-circle-check','label'=>'Read Today','value'=>number_format($stats['read_today']),'class'=>'success'])
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="panel">
            <div class="panel-header flex-wrap">
                <div class="notification-filter-links">
                    <a class="btn btn-sm {{ !request('severity') ? 'btn-primary' : 'btn-soft' }}" href="{{ route('notifications.index') }}">All</a>
                    <a class="btn btn-sm {{ request('severity') === 'critical' ? 'btn-primary' : 'btn-soft' }}" href="{{ route('notifications.index',['severity'=>'critical']) }}">Critical</a>
                    <a class="btn btn-sm {{ request('severity') === 'warning' ? 'btn-primary' : 'btn-soft' }}" href="{{ route('notifications.index',['severity'=>'warning']) }}">Warnings</a>
                    <a class="btn btn-sm {{ request('severity') === 'info' ? 'btn-primary' : 'btn-soft' }}" href="{{ route('notifications.index',['severity'=>'info']) }}">Information</a>
                </div>
                @permission('notifications','update')
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-soft btn-sm"><i class="fa-solid fa-check-double me-2"></i>Mark all read</button></form>
                @endpermission
            </div>
            <div class="notification-list">
                @forelse($notifications as $notification)
                    @php
                        $icons = ['amc_expiry'=>'fa-screwdriver-wrench','warranty_expiry'=>'fa-shield-halved','user_created'=>'fa-user-plus','user_deleted'=>'fa-user-minus','asset_assigned'=>'fa-laptop-file','report_generated'=>'fa-file-lines','vendor_changed'=>'fa-handshake'];
                    @endphp
                    <article class="notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                        <span class="alert-icon {{ $notification->severity }}"><i class="fa-solid {{ $icons[$notification->event_type] ?? 'fa-bell' }}"></i></span>
                        <div class="notification-content">
                            <div class="d-flex align-items-center gap-2"><h3>{{ $notification->title }}</h3>@if(!$notification->read_at)<span class="unread-dot"></span>@endif</div>
                            <p>{{ $notification->message }}</p>
                            <div class="notification-meta flex-wrap">
                                <span class="badge-soft">{{ $notification->module }}</span>
                                <span><i class="fa-regular fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}</span>
                                @if($notification->emailed_at)
                                    <span class="delivery-status sent"><i class="fa-solid fa-envelope-circle-check me-1"></i>Email sent</span>
                                @elseif($notification->email_error)
                                    <span class="delivery-status failed" title="{{ $notification->email_error }}"><i class="fa-solid fa-envelope-circle-xmark me-1"></i>Email failed</span>
                                @else
                                    <span class="delivery-status"><i class="fa-regular fa-envelope me-1"></i>In-app only</span>
                                @endif
                            </div>
                        </div>
                        @if(app(\App\Services\PermissionService::class)->allows('notifications','update') || app(\App\Services\PermissionService::class)->allows('notifications','delete'))<div class="notification-actions dropdown">
                            <button class="btn btn-soft btn-icon" data-bs-toggle="dropdown" aria-label="Notification actions"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @permission('notifications','update')
                                    @if(!$notification->read_at)
                                        <li><form method="POST" action="{{ route('notifications.read',$notification) }}">@csrf @method('PATCH')<button class="dropdown-item"><i class="fa-solid fa-check me-2"></i>Mark as read</button></form></li>
                                    @endif
                                @endpermission
                                @permission('notifications','delete')
                                    <li><form method="POST" action="{{ route('notifications.destroy',$notification) }}" data-confirm data-confirm-title="Delete notification?" data-confirm-message="This notification will be permanently removed." data-confirm-label="Delete">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="fa-regular fa-trash-can me-2"></i>Delete</button></form></li>
                                @endpermission
                            </ul>
                        </div>@endif
                    </article>
                @empty
                    <div class="empty-notifications"><i class="fa-regular fa-bell-slash"></i><strong>No notifications found</strong><span>New system events will appear here automatically.</span></div>
                @endforelse
            </div>
            @include('partials.pagination',['paginator'=>$notifications])
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel mb-3">
            <div class="panel-header"><div><h2>Alert Summary</h2><p>Last 24 hours by source</p></div></div>
            <div class="panel-body">
                @if($sourceSummary->isNotEmpty())<div class="chart-wrap" style="height:230px"><canvas id="alertChart"></canvas></div>
                @else<div class="empty-chart-state"><i class="fa-solid fa-chart-pie"></i><span>No events in the last 24 hours.</span></div>@endif
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><div><h2>Delivery Channels</h2><p>Current global channel status</p></div></div>
            <div class="panel-body">
                <div class="channel-row"><span class="channel-icon"><i class="fa-solid fa-bell"></i></span><div><strong>In-app</strong><small>{{ $preferences->where('in_app_enabled',true)->count() }} of {{ $preferences->count() }} alerts enabled</small></div><span class="badge-soft success ms-auto">Active</span></div>
                <div class="channel-row"><span class="channel-icon"><i class="fa-solid fa-envelope"></i></span><div><strong>Email</strong><small>{{ $smtp->enabled ? ($smtp->notification_emails ?: 'No recipient') : 'Configure in Settings → SMTP' }}</small></div><span class="badge-soft {{ $smtp->enabled ? 'success' : 'muted' }} ms-auto">{{ $smtp->enabled ? 'Active' : 'Off' }}</span></div>
            </div>
        </div>
    </div>
</div>

@if($canManageAlertRules)
<div class="panel mt-3" id="alertRules">
    <form method="POST" action="{{ route('notifications.preferences') }}">
        @csrf
        <div class="panel-header"><div><h2>Alert Rules & Channels</h2><p>Choose which events use in-app and email delivery</p></div><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save Rules</button></div>
        <div class="table-responsive">
            <table class="table data-table">
                <thead><tr><th>Alert</th><th>Trigger</th><th class="text-center">In-app</th><th class="text-center">Email</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($preferences as $preference)
                    <tr>
                        <td><strong>{{ $preference->label }}</strong><small class="d-block text-secondary">{{ Str::headline($preference->event_type) }}</small></td>
                        <td>
                            @if($preference->days_before !== null)
                                <div class="days-before-control"><input class="form-control" type="number" name="preferences[{{ $preference->event_type }}][days_before]" value="{{ $preference->days_before }}" min="1" max="365"><span>days before expiry</span></div>
                            @else Event occurs in the system @endif
                        </td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="preferences[{{ $preference->event_type }}][in_app]" value="1" @checked($preference->in_app_enabled)></td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="preferences[{{ $preference->event_type }}][email]" value="1" @checked($preference->email_enabled)></td>
                        <td><span class="badge-soft {{ $preference->in_app_enabled || $preference->email_enabled ? 'success' : 'muted' }}">{{ $preference->in_app_enabled || $preference->email_enabled ? 'Active' : 'Paused' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </form>
</div>
@endif
@endsection

@push('scripts')
@if($sourceSummary->isNotEmpty())
<script>
(()=>{
 const labels=@json($sourceSummary->keys()->values()),values=@json($sourceSummary->values());
 let chart;
 const draw=()=>{chart?.destroy();chart=new Chart(document.getElementById('alertChart'),{type:'doughnut',data:{labels,datasets:[{data:values,backgroundColor:['#3152ac','#0abfbd','#f59e0b','#ef4444','#6b7ed2','#10b981','#94a3b8'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:7,padding:12}}}}})};
 draw();window.addEventListener('themechange',draw);
})();
</script>
@endif
@endpush
