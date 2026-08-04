@extends('layouts.app')
@section('title','Users')
@section('content')
@include('partials.page-header',['title'=>$selectedRole ? $selectedRole.' Access Accounts' : 'Users','description'=>$selectedRole ? 'Manage dashboard login accounts assigned to the '.$selectedRole.' role.' : 'Manage users and their assigned assets.'])

<div class="row row-cols-2 row-cols-md-3 g-3 mb-3">
    @include('partials.stat-card',['icon'=>'fa-users','label'=>$selectedRole ? 'Login Accounts' : 'Users','value'=>number_format($stats['total'])])
    @include('partials.stat-card',['icon'=>'fa-user-check','label'=>'Active','value'=>number_format($stats['active']),'class'=>'success'])
    @include('partials.stat-card',['icon'=>'fa-user-slash','label'=>'Inactive','value'=>number_format($stats['inactive']),'class'=>'warning'])
</div>

<div class="panel">
    <div class="panel-header">
        <div><h2>{{ $selectedRole ? $selectedRole.' Login Directory' : 'Asset Assignee Directory' }}</h2><p>{{ $users->total() }} {{ $selectedRole ? 'dashboard accounts' : 'non-login users' }} available</p></div>
        @permission('users','create')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="fa-solid {{ $selectedRole ? 'fa-key' : 'fa-user-plus' }} me-2"></i>{{ $selectedRole ? 'Add Access Account' : 'Add User' }}</button>
        @endpermission
    </div>
    <div class="table-toolbar">
        <form class="table-search" method="GET">@if($selectedRole)<input type="hidden" name="role" value="{{ $selectedRole }}">@endif<i class="fa-solid fa-magnifying-glass"></i><input name="search" type="search" value="{{ request('search') }}" placeholder="Search name, email, contact or user ID…"></form>
        @if(request('search'))<a class="btn btn-soft" href="{{ route('users.index', $selectedRole ? ['role'=>$selectedRole] : []) }}"><i class="fa-solid fa-xmark me-2"></i>Clear</a>@endif
    </div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>User</th><th>User ID</th><th>{{ $selectedRole ? 'Access Role' : 'Account Type' }}</th><th>Contact</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><div class="cell-title">
                        @if($user->image_path)
                            <img src="{{ \App\Support\PublicUrl::storage($user->image_path) }}" alt="{{ $user->name }}" style="width:42px;height:42px;object-fit:cover;border-radius:50%" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                            <span class="avatar" hidden>{{ collect(explode(' ', $user->name))->filter()->take(2)->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))->join('') }}</span>
                        @else
                            <span class="avatar">{{ collect(explode(' ', $user->name))->filter()->take(2)->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))->join('') }}</span>
                        @endif
                        <span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
                    </div></td>
                    <td><strong>{{ $user->unique_id ?: '—' }}</strong></td>
                    <td>
                        @if($user->login_enabled)
                            <span class="badge-soft success"><i class="fa-solid fa-key me-1"></i>{{ $user->role }}</span>
                        @else
                            <span class="badge-soft muted"><i class="fa-solid fa-user-tag me-1"></i>User</span>
                        @endif
                    </td>
                    <td>{{ $user->contact ?: '—' }}</td>
                    <td title="{{ $user->address }}">{{ Str::limit($user->address, 45) ?: '—' }}</td>
                    <td><span class="badge-soft {{ $user->status === 'Active' ? 'success' : 'muted' }}">{{ $user->status }}</span></td>
                    <td>
                        @permission('users','assign')<button
                            class="btn btn-soft btn-icon assign-asset-button"
                            type="button"
                            title="Assign asset"
                            data-bs-toggle="modal"
                            data-bs-target="#assignAssetModal"
                            data-user-name="{{ $user->name }}"
                            data-action="{{ route('users.assign-asset', $user) }}"
                            @disabled($availableAssets->isEmpty())
                        ><i class="fa-solid fa-laptop-file"></i></button>@endpermission
                        @permission('users','update')
                            <button class="btn btn-soft btn-icon" title="Edit user" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endpermission
                        @permission('users','delete')
                        <form class="d-inline" method="POST" action="{{ route('users.destroy', $user) }}" data-confirm data-confirm-title="Delete User?" data-confirm-message="This will permanently delete {{ $user->name }}." data-confirm-label="Delete User">@csrf @method('DELETE')<button class="btn btn-soft btn-icon" title="Delete user"><i class="fa-regular fa-trash-can text-danger"></i></button></form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5 text-secondary"><i class="fa-solid fa-users fa-2x mb-3 d-block"></i>No {{ $selectedRole ? strtolower($selectedRole).' access accounts' : 'users' }} found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination',['paginator'=>$users])
</div>

@permission('users','create')
    @include('users.form',['user'=>null])
@endpermission
@permission('users','update')
    @foreach($users as $user)
        @include('users.form',['user'=>$user])
    @endforeach
@endpermission

@permission('users','assign')
<div class="modal fade" id="assignAssetModal" tabindex="-1" aria-labelledby="assignAssetModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="#" id="assignAssetForm">
                @csrf
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="assignAssetModalTitle">Assign Asset</h2>
                        <p class="mb-0 mt-1 text-secondary small">Select an available asset for <strong id="assignAssetUser">this user</strong>.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($availableAssets->isNotEmpty())
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <label class="form-label" for="assignAssetType">Asset Type *</label>
                                <select class="form-select" id="assignAssetType" name="asset_type_id" required>
                                    <option value="">Select type</option>
                                    @foreach($availableAssets->pluck('type')->filter()->unique('id')->sortBy('name') as $availableType)
                                        <option value="{{ $availableType->id }}">{{ $availableType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-7">
                                <label class="form-label" for="assignAssetSelect">Available Asset *</label>
                                <select class="form-select" id="assignAssetSelect" name="asset_id" required disabled>
                                    <option value="">Select type first</option>
                                    @foreach($availableAssets as $availableAsset)
                                        <option value="{{ $availableAsset->id }}" data-type="{{ $availableAsset->asset_type_id }}" hidden>
                                            {{ $availableAsset->asset_tag }} — {{ $availableAsset->name }} ({{ $availableAsset->status }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="assign-asset-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Only unassigned assets are listed. In Stock assets become Active after assignment.</span>
                        </div>
                    @else
                        <div class="empty-assignment-state">
                            <i class="fa-solid fa-laptop-circle-xmark"></i>
                            <strong>No assets available</strong>
                            <span>Add a new asset or unassign an existing one first.</span>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" @disabled($availableAssets->isEmpty())>
                        <i class="fa-solid fa-link me-2"></i>Assign Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpermission
@endsection

@push('scripts')
<script>
@permission('users','assign')
    document.getElementById('assignAssetModal')?.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const form = document.getElementById('assignAssetForm');
        const user = document.getElementById('assignAssetUser');
        const type = document.getElementById('assignAssetType');
        const select = document.getElementById('assignAssetSelect');

        form.action = button.dataset.action;
        user.textContent = button.dataset.userName;
        if (type) type.value = '';
        if (select) {
            select.value = '';
            select.disabled = true;
            select.options[0].textContent = 'Select type first';
            [...select.options].slice(1).forEach(option => option.hidden = true);
        }
    });

    document.getElementById('assignAssetType')?.addEventListener('change', function () {
        const select = document.getElementById('assignAssetSelect');
        const selectedType = this.value;

        select.value = '';
        select.disabled = !selectedType;
        select.options[0].textContent = selectedType ? 'Select an asset' : 'Select type first';
        [...select.options].slice(1).forEach(option => {
            option.hidden = option.dataset.type !== selectedType;
        });
    });
@endpermission
</script>
@endpush
