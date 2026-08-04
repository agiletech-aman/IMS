@extends('layouts.app')
@section('title','Backup & Recovery')
@section('content')
@php
    $formatBytes = fn (int $bytes) => $bytes > 0
        ? number_format($bytes / (1024 ** min((int) floor(log($bytes, 1024)), 4)), 2).' '.(['B','KB','MB','GB','TB'][min((int) floor(log($bytes, 1024)), 4)])
        : '0 B';
    $healthClass = ['healthy' => 'success', 'warning' => 'warning', 'critical' => 'danger'][$health['level']];
    $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
@endphp

<div class="page-heading">
    <div>
        <p class="eyebrow">Business continuity</p>
        <h1>Backup & Recovery</h1>
        <p>Private, verified restore points for the IMS database and uploaded files.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-soft" type="button" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            <i class="fa-solid fa-calendar-plus me-2"></i>New Schedule
        </button>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createBackupModal">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Create Backup
        </button>
    </div>
</div>

<div class="row row-cols-2 row-cols-xl-4 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-box-archive','label'=>'Total Backups','value'=>number_format($stats['total'])])
    @include('partials.stat-card',['icon'=>'fa-circle-check','label'=>'Successful','value'=>number_format($stats['successful']),'class'=>'success'])
    @include('partials.stat-card',['icon'=>'fa-circle-xmark','label'=>'Failed / Corrupted','value'=>number_format($stats['failed']),'class'=>'danger'])
    @include('partials.stat-card',['icon'=>'fa-hard-drive','label'=>'Storage Used','value'=>$formatBytes($stats['storage']),'class'=>'info'])
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="panel h-100">
            <div class="panel-header">
                <div><h2>Protection Overview</h2><p>Current backup freshness and upcoming automation.</p></div>
                <span class="badge-soft {{ $healthClass }}"><i class="fa-solid fa-shield-heart me-1"></i>{{ $health['label'] }}</span>
            </div>
            <div class="panel-body">
                <div class="backup-overview-grid">
                    <div>
                        <span>Last successful backup</span>
                        <strong>{{ $stats['last_successful']?->backup_number ?? 'No backup yet' }}</strong>
                        <small>{{ $stats['last_successful']?->completed_at?->format('d M Y, h:i A') ?? 'Create a backup to establish protection.' }}</small>
                    </div>
                    <div>
                        <span>Next scheduled backup</span>
                        <strong>{{ $health['next_scheduled'] ? \Illuminate\Support\Carbon::parse($health['next_scheduled'])->format('d M Y, h:i A') : 'Not scheduled' }}</strong>
                        <small>{{ $schedules->where('enabled', true)->count() }} active schedule(s)</small>
                    </div>
                    <div>
                        <span>Backup health</span>
                        <strong>{{ $health['label'] }}</strong>
                        <small>{{ $health['issues'] ? implode(' ', $health['issues']) : 'Storage, freshness, and integrity checks are clear.' }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="panel-header"><div><h2>Server Capabilities</h2><p>Detected backup methods.</p></div></div>
            <div class="panel-body backup-capabilities">
                <div><span><i class="fa-solid fa-terminal"></i>Shell database tools</span><b class="{{ $capabilities['shell'] ? 'text-success' : 'text-warning' }}">{{ $capabilities['shell'] ? 'Available' : 'PHP fallback' }}</b></div>
                <div><span><i class="fa-solid fa-file-zipper"></i>ZipArchive</span><b class="{{ $capabilities['zip'] ? 'text-success' : 'text-danger' }}">{{ $capabilities['zip'] ? 'Available' : 'Unavailable' }}</b></div>
                <div><span><i class="fa-brands fa-php"></i>PDO export fallback</span><b class="{{ $capabilities['php_export'] ? 'text-success' : 'text-danger' }}">{{ $capabilities['php_export'] ? 'Available' : 'Unavailable' }}</b></div>
            </div>
        </div>
    </div>
</div>

<div class="panel mb-3">
    <div class="panel-header">
        <div><h2>Automatic Schedules</h2><p>Daily, weekly, and monthly policies with count- or age-based retention.</p></div>
        <span class="badge-soft info">{{ $schedules->where('enabled', true)->count() }} active</span>
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Schedule</th><th>Backup</th><th>Frequency</th><th>Retention</th><th>Last Run</th><th>Next Run</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($schedules as $schedule)
                <tr>
                    <td><strong>{{ $schedule->name }}</strong><small class="d-block text-secondary">Created by {{ $schedule->created_by ?: 'System' }}</small></td>
                    <td><span class="badge-soft info">{{ ucfirst($schedule->backup_type) }}</span></td>
                    <td>
                        <strong>{{ ucfirst($schedule->frequency) }} at {{ substr($schedule->run_at,0,5) }}</strong>
                        <small class="d-block text-secondary">
                            @if($schedule->frequency === 'weekly'){{ $days[$schedule->day_of_week] }}
                            @elseif($schedule->frequency === 'monthly')Day {{ $schedule->day_of_month }}
                            @else Every day @endif
                        </small>
                    </td>
                    <td>{{ $schedule->retention_value }} {{ $schedule->retention_type === 'count' ? 'backups' : 'days' }}</td>
                    <td>{{ $schedule->last_run_at?->format('d M Y, h:i A') ?? 'Never' }}</td>
                    <td>{{ $schedule->next_run_at?->format('d M Y, h:i A') ?? 'Disabled' }}</td>
                    <td><span class="badge-soft {{ $schedule->enabled ? 'success' : 'muted' }}">{{ $schedule->enabled ? 'Enabled' : 'Disabled' }}</span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-soft btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#editSchedule{{ $schedule->id }}" title="Edit schedule"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" action="{{ route('backup.schedules.destroy',$schedule) }}" data-confirm data-confirm-title="Delete backup schedule?" data-confirm-message="Automation will stop, but existing backup files will be retained." data-confirm-label="Delete Schedule">
                                @csrf @method('DELETE')
                                <input type="hidden" name="confirmation" value="{{ $schedule->id }}">
                                <button class="btn btn-soft btn-icon text-danger" title="Delete schedule"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-report-state"><i class="fa-solid fa-calendar-xmark"></i><strong>No automatic schedules</strong><span>Create a schedule to automate backups and retention.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel mb-3">
    <div class="panel-header">
        <div><h2>Backup History</h2><p>{{ number_format($backups->total()) }} real backup records in private storage.</p></div>
        @if(request()->hasAny(['search','type','status']))<a class="btn btn-soft" href="{{ route('backup.index') }}"><i class="fa-solid fa-rotate-left me-2"></i>Reset</a>@endif
    </div>
    <div class="panel-body border-bottom" style="border-color:var(--border-color)!important">
        <form method="GET" class="row g-2">
            <div class="col-lg-6"><div class="table-search w-100"><i class="fa-solid fa-magnifying-glass"></i><input name="search" value="{{ request('search') }}" placeholder="Search backup number…"></div></div>
            <div class="col-6 col-lg-2"><select class="form-select" name="type"><option value="">All types</option>@foreach(['database','files','full'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">All statuses</option>@foreach(\App\Models\Backup::STATUSES as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i>Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Backup Number</th><th>Type</th><th>Date</th><th>Size</th><th>Duration</th><th>Status</th><th>Verification</th><th>Created By</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($backups as $backup)
                @php
                    $statusClass = match($backup->status){'completed','verified'=>'success','failed','corrupted'=>'danger','running'=>'warning',default=>'info'};
                    $verifyClass = match($backup->verification_status){'verified'=>'success','corrupted'=>'danger',default=>'muted'};
                @endphp
                <tr>
                    <td><a href="{{ route('backup.show',$backup) }}"><strong>{{ $backup->backup_number }}</strong></a><small class="d-block text-secondary">{{ $backup->schedule?->name ?: 'Manual backup' }}</small></td>
                    <td><span class="badge-soft info">{{ ucfirst($backup->type) }}</span></td>
                    <td>{{ $backup->created_at->format('d M Y') }}<small class="d-block text-secondary">{{ $backup->created_at->format('h:i A') }}</small></td>
                    <td>{{ $backup->formattedSize() }}</td>
                    <td>{{ $backup->duration_seconds === null ? '—' : $backup->duration_seconds.' sec' }}</td>
                    <td><span class="badge-soft {{ $statusClass }}">{{ ucfirst($backup->status) }}</span></td>
                    <td><span class="badge-soft {{ $verifyClass }}">{{ ucfirst($backup->verification_status) }}</span></td>
                    <td>{{ $backup->created_by ?: 'System' }}<small class="d-block text-secondary">{{ $backup->created_by_email }}</small></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-soft btn-icon" href="{{ route('backup.show',$backup) }}" title="View details"><i class="fa-solid fa-eye"></i></a>
                            <a class="btn btn-soft btn-icon" href="{{ route('backup.logs',$backup) }}" title="View logs"><i class="fa-solid fa-list"></i></a>
                            @if($backup->isDownloadable())
                                <a class="btn btn-soft btn-icon" href="{{ route('backup.download',$backup) }}" title="Download"><i class="fa-solid fa-download"></i></a>
                                <form method="POST" action="{{ route('backup.verify',$backup) }}">@csrf<button class="btn btn-soft btn-icon" title="Verify">
    <i class="fas fa-shield-alt"></i>
</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-report-state"><i class="fa-solid fa-box-archive"></i><strong>No backups found</strong><span>Create your first private restore point.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$backups])
</div>

@if($restoreJobs->isNotEmpty())
<div class="panel">
    <div class="panel-header"><div><h2>Recent Restore Jobs</h2><p>Safety backup and recovery audit trail.</p></div></div>
    <div class="table-responsive"><table class="table data-table"><thead><tr><th>Source</th><th>Safety Backup</th><th>Requested By</th><th>Started</th><th>Status</th></tr></thead><tbody>
        @foreach($restoreJobs as $job)<tr><td>{{ $job->backup?->backup_number }}</td><td>{{ $job->safetyBackup?->backup_number ?? '—' }}</td><td>{{ $job->requested_by }}</td><td>{{ $job->started_at?->format('d M Y, h:i A') ?? '—' }}</td><td><span class="badge-soft {{ $job->status==='completed'?'success':($job->status==='failed'?'danger':'warning') }}">{{ ucfirst($job->status) }}</span></td></tr>@endforeach
    </tbody></table></div>
</div>
@endif

<div class="modal fade" id="createBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('backup.store') }}" class="modal-content panel">@csrf
        <div class="modal-header"><div><h2 class="modal-title fs-5">Create Backup</h2><small class="text-secondary">The request runs immediately and stores its file privately.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><label class="form-label">Backup type</label><div class="backup-type-options">
            @foreach([['database','fa-database','Database','SQL database export'],['files','fa-folder-tree','Uploaded Files','Private ZIP of upload roots'],['full','fa-box-archive','Full Backup','Database and uploaded files']] as [$value,$icon,$label,$copy])
                <label><input type="radio" name="type" value="{{ $value }}" @checked($value==='full')><span><i class="fa-solid {{ $icon }}"></i><b>{{ $label }}</b><small>{{ $copy }}</small></span></label>
            @endforeach
        </div></div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Create Now</button></div>
    </form></div>
</div>

<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backup.schedules.store') }}" class="modal-content panel">@csrf
        <div class="modal-header"><div><h2 class="modal-title fs-5">New Automatic Schedule</h2><small class="text-secondary">The Laravel scheduler must be active on the server.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('backup.partials.schedule-fields',['schedule'=>null])</div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Schedule</button></div>
    </form></div>
</div>

@foreach($schedules as $schedule)
<div class="modal fade" id="editSchedule{{ $schedule->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backup.schedules.update',$schedule) }}" class="modal-content panel">@csrf @method('PUT')
        <div class="modal-header"><div><h2 class="modal-title fs-5">Edit {{ $schedule->name }}</h2><small class="text-secondary">Changes recalculate the next run time.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('backup.partials.schedule-fields',['schedule'=>$schedule])</div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update Schedule</button></div>
    </form></div>
</div>
@endforeach

<style>
.backup-overview-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.backup-overview-grid>div{padding:16px;border:1px solid var(--border-color);border-radius:12px;background:var(--body-bg)}.backup-overview-grid span,.backup-overview-grid small{display:block;color:var(--text-muted);font-size:11px}.backup-overview-grid strong{display:block;margin:7px 0 5px;color:var(--text-primary);font-size:14px}.backup-capabilities{display:grid;gap:12px}.backup-capabilities>div{display:flex;justify-content:space-between;gap:15px;padding-bottom:10px;border-bottom:1px solid var(--border-color);font-size:11px}.backup-capabilities span{display:flex;align-items:center;gap:8px}.backup-capabilities i{width:18px;color:var(--accent-color)}.backup-type-options{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.backup-type-options input{position:absolute;opacity:0}.backup-type-options span{height:140px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;padding:14px;border:1px solid var(--border-color);border-radius:13px;cursor:pointer;text-align:center}.backup-type-options i{font-size:24px;color:var(--accent-color)}.backup-type-options small{color:var(--text-muted);font-size:10px}.backup-type-options input:checked+span{border-color:var(--accent-color);background:var(--accent-soft);box-shadow:0 0 0 2px color-mix(in srgb,var(--accent-color) 13%,transparent)}@media(max-width:767px){.backup-overview-grid,.backup-type-options{grid-template-columns:1fr}.backup-type-options span{height:auto;min-height:100px}}
</style>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-schedule-fields]').forEach(group=>{
 const frequency=group.querySelector('[name="frequency"]'),weekly=group.querySelector('[data-weekly]'),monthly=group.querySelector('[data-monthly]');
 const sync=()=>{weekly.hidden=frequency.value!=='weekly';monthly.hidden=frequency.value!=='monthly'};
 frequency.addEventListener('change',sync);sync();
});
</script>
@endpush
