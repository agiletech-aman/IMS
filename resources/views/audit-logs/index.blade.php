@extends('layouts.app')
@section('title','Audit Logs')
@section('content')
@php
    $permissionService = app(\App\Services\PermissionService::class);
    $isSuperAdmin = session('static_auth_user.role') === 'Administrator';

    $auditModuleMap = [
        'departments' => 'Departments',
        'sub_departments' => 'Sub Departments',
        'types' => 'Types',
        'brands' => 'Brands',
        'assets' => 'Assets',
        'users' => 'Users',
        'reports' => 'Reports',
        'audit_logs' => 'Audit Logs',
        'backup' => 'Backup & Recovery',
        'roles_permissions' => 'Roles & Permissions',
        'settings' => 'Settings',
    ];

    $auditModules = collect(config('sidebar', []))
        ->flatMap(fn ($group) => $group['items'] ?? [])
        ->filter(function ($item) use ($permissionService) {
            $permissionModule = $item[5] ?? null;
            return $permissionModule && $permissionService->allows($permissionModule);
        })
        ->map(function ($item) use ($auditModuleMap) {
            $permissionModule = $item[5];
            return [
                'value' => $auditModuleMap[$permissionModule] ?? $item[2],
                'label' => $auditModuleMap[$permissionModule] ?? $item[2],
            ];
        })
        ->unique('value')
        ->values();
@endphp
<div class="page-heading">
    <div><p class="eyebrow">Enterprise workspace</p><h1>Audit Logs</h1><p>Complete activity history for assets, users, masters, vendors, complaints, alerts, reports, and settings.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($permissionService->allows('audit_logs','export'))
            <a class="btn btn-soft" href="{{ route('audit-logs.export', request()->query()) }}"><i class="fa-solid fa-download me-2"></i>Export Logs</a>
        @endif
        @if($isSuperAdmin)
            <form method="POST" action="{{ route('audit-logs.clear') }}"
                  data-confirm
                  data-confirm-title="Clear Logs for Data Optimization?"
                  data-confirm-message="This maintenance feature is intended only for database optimization when audit data has grown significantly. It permanently removes the existing audit history and cannot be undone. Continue only after exporting and securely retaining a CSV backup."
                  data-confirm-label="Optimize & Clear">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger"
                        type="submit"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        title="For database optimization only. Use this when audit volume affects storage or performance. Export and securely retain a CSV backup first; cleared records cannot be recovered.">
                    <i class="fa-solid fa-database me-2"></i>Optimize Audit Data
                </button>
            </form>
        @endif
    </div>
</div>

<div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-clock-rotate-left','label'=>'Events Today','value'=>number_format($stats['today'])])
    @include('partials.stat-card',['icon'=>'fa-right-to-bracket','label'=>'Login Events','value'=>number_format($stats['logins']),'class'=>'info'])
    @include('partials.stat-card',['icon'=>'fa-pen','label'=>'Data Changes','value'=>number_format($stats['changes']),'class'=>'warning'])
    @include('partials.stat-card',['icon'=>'fa-triangle-exclamation','label'=>'Failed / Blocked','value'=>number_format($stats['failed']),'class'=>'danger'])
</div>

<div class="panel mb-3">
    <div class="panel-header">
        <div><h2>Filter Activity</h2><p>Search and narrow the audit trail by module, action, result, or date.</p></div>
        @if(request()->hasAny(['search','module','action','result','date_from','date_to']))
            <a class="btn btn-soft" href="{{ route('audit-logs.index') }}"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>
        @endif
    </div>
    <div class="panel-body">
        <form method="GET" action="{{ route('audit-logs.index') }}">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label">Search</label>
                    <div class="table-search w-100"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="User, description, email or IP…"></div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Module</label>

                    <select class="form-select" name="module">
                        <option value="">All modules</option>
                        @foreach($auditModules as $auditModule)
                            <option value="{{ $auditModule['value'] }}" @selected(request('module') === $auditModule['value'])>
                                {{ $auditModule['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Action</label>
                    <select class="form-select" name="action">
                        <option value="">All actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Result</label>
                    <select class="form-select" name="result">
                        <option value="">All results</option>
                        @foreach(['Success','Failed','Blocked'] as $result)
                            <option value="{{ $result }}" @selected(request('result') === $result)>{{ $result }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i>Apply</button>
                </div>
                <div class="col-md-6">
                    <label class="form-label">From Date</label>
                    <input class="form-control" type="date" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">To Date</label>
                    <input class="form-control" type="date" name="date_to" value="{{ request('date_to') }}">
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div><h2>Activity Trail</h2><p>{{ number_format($logs->total()) }} recorded events</p></div>
        <span class="badge-soft success"><i class="fa-solid fa-shield-halved me-1"></i>Database tracked</span>
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Date & Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th><th>Result</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $actionClass = match ($log->action) {
                        'CREATE', 'ASSIGN', 'LOGIN' => 'success',
                        'DELETE', 'LOGIN FAILED' => 'danger',
                        'UPDATE', 'EXPORT', 'GENERATE' => 'warning',
                        default => 'muted',
                    };
                @endphp
                <tr>
                    <td><strong>{{ $log->created_at->format($systemSettings?->date_format ?? 'd M Y') }}</strong><small class="d-block text-secondary">{{ $log->created_at->format('h:i:s A') }}</small></td>
                    <td><strong>{{ $log->actor_name }}</strong><small class="d-block text-secondary">{{ $log->actor_role ? \App\Support\RoleLabel::display($log->actor_role) : ($log->actor_email ?: 'Automated process') }}</small></td>
                    <td><span class="badge-soft {{ $actionClass }}">{{ $log->action }}</span></td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->description }}</td>
                    <td><code>{{ $log->ip_address ?: 'System' }}</code></td>
                    <td><span class="badge-soft {{ $log->result === 'Success' ? 'success' : 'danger' }}">{{ $log->result }}</span></td>
                    <td>
                        @if($log->old_values || $log->new_values || $log->metadata)
                            <button class="btn btn-soft btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#auditDetails{{ $log->id }}" title="View changes"><i class="fa-solid fa-eye"></i></button>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                </tr>

            @empty
                <tr><td colspan="8"><div class="empty-report-state"><i class="fa-solid fa-clock-rotate-left"></i><strong>No audit events found</strong><span>New activity will appear here automatically.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$logs])
</div>

@foreach($logs as $log)
    @if($log->old_values || $log->new_values || $log->metadata)
        <div class="modal fade" id="auditDetails{{ $log->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content panel">
                    <div class="modal-header">
                        <div><h2 class="modal-title fs-5">{{ $log->action }} · {{ $log->module }}</h2><small class="text-secondary">{{ $log->description }}</small></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            @if($log->old_values)
                                <div class="col-md-6"><label class="form-label">Previous Values</label><pre class="audit-json">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                            @endif
                            @if($log->new_values)
                                <div class="col-md-6"><label class="form-label">New Values</label><pre class="audit-json">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                            @endif
                            @if($log->metadata)
                                <div class="col-12"><label class="form-label">Event Details</label><pre class="audit-json">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

<style>
.audit-json{margin:0;max-height:320px;overflow:auto;padding:1rem;border:1px solid var(--border-color);border-radius:12px;background:var(--body-bg);color:var(--text-color);font-size:.78rem;white-space:pre-wrap;word-break:break-word}
</style>
@endsection

