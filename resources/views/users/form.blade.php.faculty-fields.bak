@php
    $editing = isset($user) && $user;
    $modalId = $editing ? 'editUserModal'.$user->id : 'createUserModal';
    $action = $editing ? route('users.update', $user) : route('users.store');
    $value = fn ($field, $default = '') => old($field, $editing ? $user->{$field} : $default);
    $isLoginAccount = $editing ? $user->login_enabled : (bool) $selectedRole;
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content panel">
        <div class="modal-header" style="border-color:var(--border-color)"><div><h2 class="modal-title fs-6">{{ $editing ? 'Edit' : 'Add' }} {{ $isLoginAccount ? ($selectedRole ?: $user->role).' Access Account' : 'User' }}</h2><small class="text-secondary">{{ $isLoginAccount ? 'This account can sign in to the dashboard.' : 'Manage user details and asset assignment.' }}</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">@csrf @if($editing) @method('PUT') @endif
            <input type="hidden" name="login_enabled" value="{{ $isLoginAccount ? '1' : '0' }}">
            <div class="modal-body p-4"><div class="row g-3">
                <div class="col-md-8"><label class="form-label">Name *</label><input class="form-control" name="name" value="{{ $value('name') }}" placeholder="Enter full name" required></div>
                <div class="col-md-4"><label class="form-label">User ID</label><input class="form-control" value="{{ $editing ? $user->unique_id : 'USR-Auto' }}" readonly><small class="text-secondary">Generated automatically</small></div>
                <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="{{ $value('email') }}" placeholder="name@company.com" required></div>
                <div class="col-md-6"><label class="form-label">Contact</label><input class="form-control" name="contact" value="{{ $value('contact') }}" placeholder="Enter phone number"></div>
                <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3" placeholder="Enter complete address">{{ $value('address') }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Profile Image {{ $editing ? '(leave blank to keep current)' : '' }}</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"><small class="text-secondary">JPG, PNG or WebP up to 2 MB</small></div>
                @if($isLoginAccount)
                    <div class="col-md-3"><label class="form-label">Access Role *</label><select class="form-select" name="role" required>@foreach($roles as $role)<option value="{{ $role }}" @selected($value('role', $selectedRole ?: 'Viewer') === $role)>{{ $role }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Login Password {{ $editing ? '' : '*' }}</label><input class="form-control" type="password" name="password" minlength="8" placeholder="{{ $editing ? 'Leave blank to keep' : 'Minimum 8 characters' }}" @required(!$editing) autocomplete="new-password"></div>
                @else
                    <input type="hidden" name="role" value="Viewer">
                @endif
                <div class="{{ $isLoginAccount ? 'col-md-6' : 'col-md-6' }}"><label class="form-label">Status *</label><select class="form-select" name="status" required><option value="Active" @selected($value('status', 'Active') === 'Active')>Active</option><option value="Inactive" @selected($value('status', 'Active') === 'Inactive')>Inactive</option></select></div>
            </div></div>
            <div class="modal-footer" style="border-color:var(--border-color)"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">{{ $editing ? 'Save Changes' : ($isLoginAccount ? 'Create Access Account' : 'Add User') }}</button></div>
        </form>
    </div></div>
</div>
