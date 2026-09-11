@extends('layouts.app')
@section('title',$backup->backup_number)
@section('content')
@php($statusClass = in_array($backup->status,['completed','verified']) ? 'success' : (in_array($backup->status,['failed','corrupted']) ? 'danger' : 'warning'))
<div class="page-heading">
    <div><p class="eyebrow">Backup details</p><h1>{{ $backup->backup_number }}</h1><p>{{ ucfirst($backup->type) }} backup created {{ $backup->created_at->format('d M Y, h:i A') }}.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-soft" href="{{ route('backup.index') }}"><i class="fa-solid fa-arrow-left me-2"></i>History</a>
        <a class="btn btn-soft" href="{{ route('backup.logs',$backup) }}"><i class="fa-solid fa-list me-2"></i>View Logs</a>
        @if($backup->isDownloadable())<a class="btn btn-primary" href="{{ route('backup.download',$backup) }}"><i class="fa-solid fa-download me-2"></i>Download</a>@endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8"><div class="panel h-100"><div class="panel-header"><div><h2>Backup Record</h2><p>Immutable file identity and execution result.</p></div><span class="badge-soft {{ $statusClass }}">{{ ucfirst($backup->status) }}</span></div><div class="panel-body">
        <dl class="backup-detail-grid">
            <div><dt>Type</dt><dd>{{ ucfirst($backup->type) }}</dd></div><div><dt>Verification</dt><dd>{{ ucfirst($backup->verification_status) }}</dd></div>
            <div><dt>Size</dt><dd>{{ $backup->formattedSize() }}</dd></div><div><dt>Duration</dt><dd>{{ $backup->duration_seconds === null ? '—' : $backup->duration_seconds.' seconds' }}</dd></div>
            <div><dt>Created by</dt><dd>{{ $backup->created_by ?: 'System' }}</dd></div><div><dt>Schedule</dt><dd>{{ $backup->schedule?->name ?: 'Manual' }}</dd></div>
            <div><dt>Database method</dt><dd>{{ $backup->database_method ?: 'Not applicable' }}</dd></div><div><dt>Private file</dt><dd>{{ $backup->file_name ?: 'Not created' }}</dd></div>
            @if($backup->error_message)<div class="wide"><dt>Error</dt><dd class="text-danger">{{ $backup->error_message }}</dd></div>@endif
        </dl>
    </div></div></div>
    <div class="col-xl-4"><div class="panel h-100"><div class="panel-header"><div><h2>Secure Actions</h2><p>Destructive actions require confirmation.</p></div></div><div class="panel-body d-grid gap-2">
        @if($backup->isDownloadable())
            <form method="POST" action="{{ route('backup.verify',$backup) }}">@csrf<button class="btn btn-soft w-100"><i class="fa-solid fa-shield-check me-2"></i>Verify Integrity</button></form>
        @endif
        @if($backup->isDownloadable() && in_array($backup->type,['database','full']))
            <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#restoreModal"><i class="fa-solid fa-clock-rotate-left me-2"></i>Restore Database</button>
        @endif
        <form method="POST" action="{{ route('backup.destroy',$backup) }}" data-confirm data-confirm-title="Delete {{ $backup->backup_number }}?" data-confirm-message="The private backup file and its logs will be permanently deleted. This cannot be undone." data-confirm-label="Delete Backup">
            @csrf @method('DELETE')<input type="hidden" name="confirmation" value="{{ $backup->backup_number }}">
            <button class="btn btn-outline-danger w-100" @disabled($backup->restoreJobs->isNotEmpty())><i class="fa-solid fa-trash me-2"></i>Delete Backup</button>
        </form>
    </div></div></div>
</div>

<div class="panel"><div class="panel-header"><div><h2>Execution Timeline</h2><p>{{ $backup->logs->count() }} recorded events.</p></div></div><div class="table-responsive"><table class="table data-table"><thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Message</th></tr></thead><tbody>
@foreach($backup->logs->take(10) as $log)<tr><td>{{ $log->created_at->format('d M Y, h:i:s A') }}</td><td><span class="badge-soft {{ $log->level==='error'?'danger':($log->level==='success'?'success':'info') }}">{{ ucfirst($log->level) }}</span></td><td>{{ str($log->event)->headline() }}</td><td>{{ $log->message }}</td></tr>@endforeach
</tbody></table></div></div>

@if($backup->isDownloadable() && in_array($backup->type,['database','full']))
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('backup.restore',$backup) }}" class="modal-content panel">@csrf
<div class="modal-header"><div><h2 class="modal-title fs-5">Restore Database</h2><small class="text-danger">This replaces current application data.</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="alert alert-warning"><strong>A safety database backup will be created first.</strong><br>Do not close the request while recovery is running. Backup control tables are included in exports but protected during import so the live recovery trail is preserved.</div><label class="form-label">Enter <strong>{{ $backup->backup_number }}</strong> to confirm</label><input class="form-control" name="confirmation" required autocomplete="off"></div>
<div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Create Safety Backup & Restore</button></div>
</form></div></div>
@endif
<style>.backup-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:0}.backup-detail-grid>div{padding:13px;border-bottom:1px solid var(--border-color)}.backup-detail-grid .wide{grid-column:1/-1}.backup-detail-grid dt{color:var(--text-muted);font-size:10px;text-transform:uppercase;letter-spacing:.6px}.backup-detail-grid dd{margin:5px 0 0;color:var(--text-primary);font-size:12px;word-break:break-word}@media(max-width:575px){.backup-detail-grid{grid-template-columns:1fr}.backup-detail-grid .wide{grid-column:auto}}</style>
@endsection
