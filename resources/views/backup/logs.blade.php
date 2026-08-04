@extends('layouts.app')
@section('title',$backup->backup_number.' Logs')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Backup execution log</p><h1>{{ $backup->backup_number }}</h1><p>Technical events for creation, verification, retention, and restore operations.</p></div><a class="btn btn-soft" href="{{ route('backup.show',$backup) }}"><i class="fa-solid fa-arrow-left me-2"></i>Backup Details</a></div>
<div class="panel"><div class="panel-header"><div><h2>Event Log</h2><p>{{ number_format($logs->total()) }} events</p></div></div><div class="table-responsive"><table class="table data-table"><thead><tr><th>Date & Time</th><th>Level</th><th>Event</th><th>Message</th><th>Context</th></tr></thead><tbody>
@forelse($logs as $log)<tr><td>{{ $log->created_at->format('d M Y') }}<small class="d-block text-secondary">{{ $log->created_at->format('h:i:s A') }}</small></td><td><span class="badge-soft {{ $log->level==='error'?'danger':($log->level==='success'?'success':'info') }}">{{ ucfirst($log->level) }}</span></td><td>{{ str($log->event)->headline() }}</td><td>{{ $log->message }}</td><td>@if($log->context)<code>{{ json_encode($log->context,JSON_UNESCAPED_SLASHES) }}</code>@else — @endif</td></tr>
@empty<tr><td colspan="5"><div class="empty-report-state"><i class="fa-solid fa-list"></i><strong>No log events</strong></div></td></tr>@endforelse
</tbody></table></div>@include('partials.pagination',['paginator'=>$logs])</div>
@endsection
