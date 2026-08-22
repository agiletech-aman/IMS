@extends('layouts.app')
@section('title','Roles & Permissions')
@section('content')
@include('partials.page-header',['title'=>'Roles & Permissions','description'=>'Control module access and View, Create, Update, Delete, Assign, Import, and Export actions for every dashboard role.'])

@php
    $permissionService = app(\App\Services\PermissionService::class);
    $canUpdatePermissions = $permissionService->allows('roles_permissions', 'update');
    $roleDetails = [
        'Asset Manager' => ['Create, assign, update, import, and maintain asset operations','fa-laptop-file','info'],
        'Sub admin' => ['Manage business modules and system operations','fa-user-gear','warning'],
        'Auditor' => ['Read-only operational and compliance access','fa-user-shield','success'],
        'Viewer' => ['View permitted dashboard information','fa-user-lock',''],
    ];
@endphp

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-3">
    @foreach($roles as $role)
        @php [$description,$icon,$class] = $roleDetails[$role]; @endphp
        <div class="col">
            <button class="panel role-card role-selector h-100 w-100 text-start {{ $loop->first ? 'is-selected' : '' }}" type="button" data-role-target="rolePanel{{ $loop->index }}">
                <div class="panel-body d-flex gap-3">
                    <span class="stat-icon"><i class="fa-solid {{ $icon }}"></i></span>
                    <div class="flex-grow-1"><h6 class="mb-1">{{ \App\Support\RoleLabel::display($role) }}</h6><p class="small text-secondary mb-3">{{ $description }}</p><span class="badge-soft {{ $class }}"><i class="fa-solid fa-shield-halved me-1"></i>Database permissions</span></div>
                </div>
            </button>
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('roles-permissions.update') }}">
    @csrf
    @foreach($roles as $role)
        <div class="panel role-permission-panel {{ $loop->first ? '' : 'd-none' }}" id="rolePanel{{ $loop->index }}">
            <div class="panel-header">
                <div><h2>{{ \App\Support\RoleLabel::display($role) }} Permissions</h2><p>Changes apply to all active {{ \App\Support\RoleLabel::display($role) }} login accounts immediately</p></div>
                @if($canUpdatePermissions)
                    <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save All Permissions</button>
                @else
                    <span class="badge-soft muted"><i class="fa-solid fa-eye me-1"></i>Read only</span>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table data-table permission-table">
                    <thead><tr><th>Module</th>@foreach($actions as $action)<th class="text-center">{{ ucfirst($action) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($modules as $moduleKey=>$module)
                        @php
                            $record = $permissions->get($role.':'.$moduleKey);
                            $administratorOnly = $moduleKey === 'administrators';
                        @endphp
                        <tr>
                            <td><div class="cell-title"><span class="mini-icon"><i class="fa-solid {{ $module['icon'] }}"></i></span><span><strong>{{ $module['label'] }}</strong><small>{{ $administratorOnly ? 'Administrator accounts only' : 'Module permission' }}</small></span></div></td>
                            @foreach($actions as $action)
                                @php
                                    $applicable = in_array($action, $module['actions'], true) && ! $administratorOnly;
                                    $checked = $applicable && (bool) $record?->getAttribute('can_'.$action);
                                @endphp
                                <td class="text-center">
                                    <input
                                        class="form-check-input permission-check"
                                        type="checkbox"
                                        name="permissions[{{ $role }}][{{ $moduleKey }}][{{ $action }}]"
                                        value="1"
                                        @checked($checked)
                                        @disabled(! $applicable || ! $canUpdatePermissions)
                                        aria-label="{{ ucfirst($action) }} {{ $module['label'] }} for {{ $role }}"
                                    >
                                    @if(!$applicable)<span class="permission-na">N/A</span>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-body border-top permission-note">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>View</strong> is the module-access switch and is required for every other action. Administrator retains unrestricted access.</span>
            </div>
        </div>
    @endforeach
</form>

<style>
.role-selector{border:1px solid var(--border-color);color:var(--text-primary);transition:.2s}
.role-selector:hover,.role-selector.is-selected{transform:translateY(-2px);border-color:var(--accent-color);box-shadow:0 12px 30px color-mix(in srgb,var(--accent-color) 13%,transparent)}
.permission-table .form-check-input{width:18px;height:18px;margin:0;cursor:pointer}
.permission-table .form-check-input:disabled{opacity:.32;cursor:not-allowed}
.permission-na{display:block;margin-top:3px;color:var(--text-muted);font-size:8px}
.permission-note{display:flex;align-items:center;gap:9px;color:var(--text-muted);font-size:11px}
.permission-note i{color:var(--accent-color)}
</style>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.role-selector').forEach(button=>button.addEventListener('click',()=>{
 document.querySelectorAll('.role-selector').forEach(item=>item.classList.toggle('is-selected',item===button));
 document.querySelectorAll('.role-permission-panel').forEach(panel=>panel.classList.toggle('d-none',panel.id!==button.dataset.roleTarget));
}));
document.querySelectorAll('.permission-table tbody tr').forEach(row=>{
 const inputs=[...row.querySelectorAll('.permission-check:not(:disabled)')];
 const view=inputs.find(input=>input.name.endsWith('[view]'));
 if(!view)return;
 inputs.forEach(input=>input.addEventListener('change',()=>{
  if(input!==view && input.checked)view.checked=true;
  if(input===view && !view.checked)inputs.forEach(action=>action.checked=false);
 }));
});
</script>
@endpush
