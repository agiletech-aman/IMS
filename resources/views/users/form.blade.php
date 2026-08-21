@php
    $isEdit = isset($user) && $user;
    $isAccessAccount = (bool) $selectedRole;

    $modalId = $isEdit
        ? 'editUserModal'.$user->id
        : 'createUserModal';

    $formAction = $isAccessAccount
        ? ($isEdit
            ? route('access-accounts.update', [
                'accessAccount' => $user,
                'role' => $selectedRole,
            ])
            : route('access-accounts.store', ['role' => $selectedRole]))
        : ($isEdit ? route('users.update', $user) : route('users.store'));
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <form
                method="POST"
                action="{{ $formAction }}"
                enctype="multipart/form-data"
            >

                @csrf

                @if($isEdit)
                    @method('PUT')
                @endif

                <input type="hidden" name="login_enabled" value="{{ $isAccessAccount ? 1 : 0 }}">

                @if(!$isAccessAccount)
                    <input type="hidden" name="status" value="Active">
                @endif

                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5">
                            @if($isAccessAccount)
                                {{ $isEdit ? 'Edit' : 'Add' }} {{ $selectedRole }} Access Account
                            @else
                                {{ $isEdit ? 'Edit User' : 'Add User' }}
                            @endif
                        </h2>
                        <p class="mb-0 mt-1 text-secondary small">
                            @if($isAccessAccount)
                                This account can sign in to the dashboard.
                            @else
                                Manage user details and asset assignment information.
                            @endif
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    @if(!$isAccessAccount)

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">User Name *</label>
                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name', $user?->name) }}"
                                    placeholder="Enter full name"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Deptt. *</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="">Select Department</option>
                                    @foreach($departments as $department)
                                        <option
                                            value="{{ $department->id }}"
                                            @selected(old('department_id', $user?->department_id) == $department->id)
                                        >
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">FB New/Old *</label>
                                <input
                                    type="text"
                                    name="fb_type"
                                    class="form-control"
                                    list="fbTypeOptions"
                                    autocomplete="off"
                                    placeholder="FB New/Old"
                                    value="{{ old('fb_type', $user?->fb_type) }}"
                                    required
                                >
                                <datalist id="fbTypeOptions">
                                    <option value="New FB">
                                    <option value="Old FB">
                                </datalist>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input
                                    type="text"
                                    name="contact"
                                    class="form-control"
                                    value="{{ old('contact', $user?->contact) }}"
                                    placeholder="Enter phone number"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="{{ old('email', $user?->email) }}"
                                    placeholder="name@company.com"
                                >
                            </div>

                            <div class="col-12">
                                <label class="form-label">Remark</label>
                                <textarea
                                    name="remark"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Enter remark"
                                >{{ old('remark', $user?->remark) }}</textarea>
                            </div>

                        </div>

                    @else

                        <div class="row g-3">

                            <div class="col-md-8">
                                <label class="form-label">Name *</label>
                                <input
                                    type="text"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name', $user?->name) }}"
                                    placeholder="Enter full name"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="{{ old('email', $user?->email) }}"
                                    placeholder="name@company.com"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact</label>
                                <input
                                    type="text"
                                    name="contact"
                                    class="form-control"
                                    value="{{ old('contact', $user?->contact) }}"
                                    placeholder="Enter phone number"
                                >
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea
                                    name="address"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Enter complete address"
                                >{{ old('address', $user?->address) }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Profile Image</label>
                                <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-secondary">JPG, PNG or WebP up to 2 MB</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Access Role *</label>
                                <select name="role" class="form-select" required>
                                    <option value="{{ $selectedRole }}" selected>{{ $selectedRole }}</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">
                                    Login Password {{ !$isEdit ? '*' : '' }}
                                </label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="{{ $isEdit ? 'Leave blank to keep current' : 'Minimum 8 characters' }}"
                                    {{ !$isEdit ? 'required' : '' }}
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option
                                        value="Active"
                                        @selected(old('status', $user?->status ?? 'Active') === 'Active')
                                    >
                                        Active
                                    </option>
                                    <option
                                        value="Inactive"
                                        @selected(old('status', $user?->status) === 'Inactive')
                                    >
                                        Inactive
                                    </option>
                                </select>
                            </div>

                        </div>

                    @endif

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        @if($isAccessAccount)
                            {{ $isEdit ? 'Update Access Account' : 'Create Access Account' }}
                        @else
                            {{ $isEdit ? 'Update User' : 'Add User' }}
                        @endif
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>
