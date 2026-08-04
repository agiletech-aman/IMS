@extends('layouts.app')
@section('title','Settings')
@section('content')
@include('partials.page-header',['title'=>'System Settings','description'=>'Configure administrators, organization defaults, notification delivery, SMTP, security, and appearance.'])

@php
    $permissionService = app(\App\Services\PermissionService::class);
    $canViewBasic = $permissionService->allows('settings_basic');
    $canUpdateBasic = $permissionService->allows('settings_basic', 'update');
    $canViewAdvanced = $permissionService->allows('settings_advanced');
    $canUpdateAdvanced = $permissionService->allows('settings_advanced', 'update');
    $settingsTabs = array_values(array_filter([
        $canViewBasic ? ['general','fa-sliders','General Settings'] : null,
        $canViewBasic ? ['company','fa-building','Company Info'] : null,
        $canManageAdmins ? ['administrators','fa-user-gear','Administrators'] : null,
        $canViewAdvanced ? ['smtp','fa-envelope','SMTP Settings'] : null,
        $canViewAdvanced ? ['notifications','fa-bell','Notification Settings'] : null,
        $canViewAdvanced ? ['security','fa-shield-halved','Security Settings'] : null,
        $canViewBasic ? ['theme','fa-palette','Theme Settings'] : null,
    ]));
    $requestedTab = session('activeSettingsTab');
    $activeTab = collect($settingsTabs)->contains(fn($tab) => $tab[0] === $requestedTab)
        ? $requestedTab
        : ($settingsTabs[0][0] ?? null);
@endphp
<div class="row g-3">
    <div class="col-lg-3">
        <div class="panel p-2"><nav class="nav nav-pills flex-column setting-nav" id="settingsTabs" role="tablist">
            @foreach($settingsTabs as $tab)
                <button class="nav-link {{ $activeTab === $tab[0] ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#{{ $tab[0] }}" type="button"><i class="fa-solid {{ $tab[1] }} me-2"></i>{{ $tab[2] }}</button>
            @endforeach
        </nav></div>
    </div>
    <div class="col-lg-9">
        <div class="panel"><div class="tab-content">
            @if($settingsTabs === [])
                <div class="panel-body empty-chart-state"><i class="fa-solid fa-lock"></i><span>No Settings feature has been assigned to this role.</span></div>
            @endif
            @if($canViewBasic)
            <div class="tab-pane fade {{ $activeTab === 'general' ? 'show active' : '' }}" id="general">
                <div class="panel-header"><div><h2>General Settings</h2><p>Regional preferences and system defaults</p></div></div>
                <div class="panel-body"><form method="POST" action="{{ route('settings.general.update') }}">@csrf<div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Application Name *</label><input class="form-control" name="application_name" value="{{ old('application_name',$settings->application_name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Default Language *</label><select class="form-select" name="language" required><option value="en" @selected($settings->language==='en')>English (India)</option><option value="hi" @selected($settings->language==='hi')>Hindi</option></select></div>
                    <div class="col-md-6"><label class="form-label">Time Zone *</label><select class="form-select" name="timezone" required>@foreach(['Asia/Kolkata'=>'Asia/Kolkata (UTC +05:30)','UTC'=>'UTC','Asia/Dubai'=>'Asia/Dubai (UTC +04:00)','Europe/London'=>'Europe/London','America/New_York'=>'America/New York'] as $value=>$label)<option value="{{ $value }}" @selected($settings->timezone===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Date Format *</label><select class="form-select" name="date_format" required><option value="d M Y" @selected($settings->date_format==='d M Y')>DD MMM YYYY</option><option value="d/m/Y" @selected($settings->date_format==='d/m/Y')>DD/MM/YYYY</option><option value="Y-m-d" @selected($settings->date_format==='Y-m-d')>YYYY-MM-DD</option></select></div>
                    <div class="col-md-3"><label class="form-label">Currency *</label><select class="form-select" name="currency" required>@foreach(['INR'=>'INR (₹)','USD'=>'USD ($)','EUR'=>'EUR (€)'] as $value=>$label)<option value="{{ $value }}" @selected($settings->currency===$value)>{{ $label }}</option>@endforeach</select></div>
                </div>@if($canUpdateBasic)<button class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save General Settings</button>@endif</form></div>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'company' ? 'show active' : '' }}" id="company">
                <div class="panel-header"><h2>Company Information</h2></div>
                <div class="panel-body"><form method="POST" action="{{ route('settings.company.update') }}">@csrf<div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Company Name *</label><input class="form-control" name="company_name" value="{{ old('company_name',$settings->company_name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Tax / GST Number</label><input class="form-control" name="tax_number" value="{{ old('tax_number',$settings->tax_number) }}"></div>
                    <div class="col-12"><label class="form-label">Registered Address</label><textarea class="form-control" name="registered_address" rows="3">{{ old('registered_address',$settings->registered_address) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Support Email</label><input class="form-control" type="email" name="support_email" value="{{ old('support_email',$settings->support_email) }}"></div>
                    <div class="col-md-6"><label class="form-label">Support Phone</label><input class="form-control" name="support_phone" value="{{ old('support_phone',$settings->support_phone) }}"></div>
                </div>@if($canUpdateBasic)<button class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Company Info</button>@endif</form></div>
            </div>
            @endif

            @if($canManageAdmins)
            <div class="tab-pane fade {{ $activeTab === 'administrators' ? 'show active' : '' }}" id="administrators">
                <div class="panel-header">
                    <div><h2>Administrator Management</h2><p>Separate full-access accounts used to administer the system</p></div>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createAdminModal"><i class="fa-solid fa-user-plus me-2"></i>Add Administrator</button>
                </div>
                <div class="panel-body pb-2">
                    <div class="smtp-info"><i class="fa-solid fa-shield-halved"></i><span>Administrator accounts are stored separately from users and role-based Viewer accounts. Passwords are securely hashed.</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead><tr><th>Administrator</th><th>Designation</th><th>Contact</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        @foreach($admins as $admin)
                            <tr>
                                <td>
                                    <div class="cell-title">
                                        @if($admin->image_path)
                                            <img src="{{ \App\Support\PublicUrl::storage($admin->image_path) }}" alt="{{ $admin->name }}" style="width:40px;height:40px;object-fit:cover;border-radius:11px" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                                            <span class="avatar" hidden>{{ collect(explode(' ', $admin->name))->filter()->take(2)->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))->join('') }}</span>
                                        @else
                                            <span class="avatar">{{ collect(explode(' ', $admin->name))->filter()->take(2)->map(fn($part) => Str::upper(Str::substr($part, 0, 1)))->join('') }}</span>
                                        @endif
                                        <span><strong>{{ $admin->name }}</strong><small>{{ $admin->email }}</small></span>
                                    </div>
                                </td>
                                <td>{{ $admin->designation ?: 'Administrator' }}</td>
                                <td>{{ $admin->phone ?: '—' }}</td>
                                <td>{{ $admin->last_login_at?->format('d M Y · h:i A') ?: 'Never' }}</td>
                                <td><span class="badge-soft {{ $admin->status === 'Active' ? 'success' : 'muted' }}">{{ $admin->status }}</span></td>
                                <td>
                                    <button class="btn btn-soft btn-icon" type="button" title="Edit administrator" data-bs-toggle="modal" data-bs-target="#editAdminModal{{ $admin->id }}"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <form class="d-inline" method="POST" action="{{ route('settings.admins.destroy', $admin) }}" data-confirm data-confirm-title="Delete Administrator?" data-confirm-message="Remove {{ $admin->name }} administrator access?" data-confirm-label="Delete Administrator">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-soft btn-icon" title="Delete administrator" @disabled(session('static_auth_user.admin_id') === $admin->id)><i class="fa-regular fa-trash-can text-danger"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="modal fade" id="createAdminModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content panel">
                        <div class="modal-header"><div><h2 class="modal-title fs-6">Add Administrator</h2><small class="text-secondary">Create a separate full-access login account</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST" action="{{ route('settings.admins.store') }}" enctype="multipart/form-data">@csrf
                            <div class="modal-body p-4"><div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Name *</label><input class="form-control" name="name" required></div>
                                <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" required></div>
                                <div class="col-md-6"><label class="form-label">Password *</label><input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" required><small class="text-secondary">Minimum 8 characters</small></div>
                                <div class="col-md-6"><label class="form-label">Profile Image</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
                                <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" placeholder="System Administrator"></div>
                                <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
                                <div class="col-md-6"><label class="form-label">Status *</label><select class="form-select" name="status" required><option>Active</option><option>Inactive</option></select></div>
                            </div></div>
                            <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fa-solid fa-user-shield me-2"></i>Create Administrator</button></div>
                        </form>
                    </div></div>
                </div>

                @foreach($admins as $admin)
                    <div class="modal fade" id="editAdminModal{{ $admin->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content panel">
                            <div class="modal-header"><div><h2 class="modal-title fs-6">Edit Administrator</h2><small class="text-secondary">{{ $admin->email }}</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form method="POST" action="{{ route('settings.admins.update', $admin) }}" enctype="multipart/form-data">@csrf @method('PUT')
                                <div class="modal-body p-4"><div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Full Name *</label><input class="form-control" name="name" value="{{ $admin->name }}" required></div>
                                    <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="{{ $admin->email }}" required></div>
                                    <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control" type="password" name="password" minlength="8" placeholder="Leave blank to keep current" autocomplete="new-password"></div>
                                    <div class="col-md-6"><label class="form-label">Profile Image</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"><small class="text-secondary">Leave blank to keep current image</small></div>
                                    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ $admin->phone }}"></div>
                                    <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" value="{{ $admin->designation }}"></div>
                                    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2">{{ $admin->address }}</textarea></div>
                                    <div class="col-md-6"><label class="form-label">Status *</label><select class="form-select" name="status" required><option @selected($admin->status === 'Active')>Active</option><option @selected($admin->status === 'Inactive')>Inactive</option></select></div>
                                </div></div>
                                <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Administrator</button></div>
                            </form>
                        </div></div>
                    </div>
                @endforeach
            </div>
            @endif

            @if($canViewAdvanced)
            <div class="tab-pane fade {{ $activeTab === 'smtp' ? 'show active' : '' }}" id="smtp">
                <div class="panel-header">
                    <div><h2>Dynamic SMTP Settings</h2><p>Saved settings override MAIL_* environment defaults at runtime</p></div>
                    <span class="badge-soft {{ $smtp->enabled ? 'success' : 'muted' }}">{{ $smtp->enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="panel-body">
                    <div class="smtp-info"><i class="fa-solid fa-database"></i><span>SMTP credentials are stored in the database. The password is encrypted using the application key and is never displayed after saving.</span></div>
                    <form method="POST" action="{{ route('settings.smtp.update') }}">@csrf
                        <div class="smtp-enable-row">
                            <div><strong>Enable email delivery</strong><small>Use these SMTP settings for notification emails</small></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked($smtp->enabled)></div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-8"><label class="form-label">SMTP Host *</label><input class="form-control" name="host" value="{{ old('host',$smtp->host) }}" placeholder="smtp.office365.com"></div>
                            <div class="col-md-4"><label class="form-label">Port *</label><input class="form-control" type="number" name="port" min="1" max="65535" value="{{ old('port',$smtp->port) }}"></div>
                            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username',$smtp->username) }}" autocomplete="off"></div>
                            <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" value="" placeholder="{{ $smtp->password ? 'Saved — leave blank to keep' : 'Enter SMTP password' }}" autocomplete="new-password"></div>
                            <div class="col-md-4"><label class="form-label">Encryption</label><select class="form-select" name="encryption"><option value="tls" @selected($smtp->encryption==='tls')>TLS</option><option value="ssl" @selected($smtp->encryption==='ssl')>SSL</option><option value="none" @selected(blank($smtp->encryption))>None</option></select></div>
                            <div class="col-md-4"><label class="form-label">From Name *</label><input class="form-control" name="from_name" value="{{ old('from_name',$smtp->from_name) }}" placeholder="Agile Tech Solutions IIM"></div>
                            <div class="col-md-4"><label class="form-label">From Email *</label><input class="form-control" type="email" name="from_address" value="{{ old('from_address',$smtp->from_address) }}"></div>
                            <div class="col-12"><label class="form-label">Alert Recipient Emails *</label><textarea class="form-control" name="notification_emails" rows="2" placeholder="admin@company.com, assets@company.com">{{ old('notification_emails',$smtp->notification_emails) }}</textarea><small class="text-secondary">Separate multiple recipients with commas, semicolons, or spaces.</small></div>
                        </div>
                        @if($canUpdateAdvanced)<button class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save SMTP Settings</button>@endif
                    </form>
                    @if($canUpdateAdvanced)<form class="d-inline" method="POST" action="{{ route('settings.smtp.test') }}">@csrf<button class="btn btn-soft mt-3"><i class="fa-solid fa-paper-plane me-2"></i>Send Test Email</button></form>@endif
                </div>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'notifications' ? 'show active' : '' }}" id="notifications">
                <form method="POST" action="{{ route('notifications.preferences') }}">@csrf
                    <div class="panel-header"><div><h2>Notification Settings</h2><p>Configure both in-app and email delivery per event</p></div></div>
                    <div class="panel-body">
                        <div class="notification-setting-head"><span>Alert</span><span>In-app</span><span>Email</span></div>
                        @foreach($preferences as $preference)
                            <div class="notification-setting-row">
                                <div><strong>{{ $preference->label }}</strong><small>{{ $preference->days_before !== null ? 'Alert '.$preference->days_before.' days before expiry' : 'Send when this event occurs' }}</small></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="preferences[{{ $preference->event_type }}][in_app]" value="1" @checked($preference->in_app_enabled)></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="preferences[{{ $preference->event_type }}][email]" value="1" @checked($preference->email_enabled)></div>
                                @if($preference->days_before !== null)<input type="hidden" name="preferences[{{ $preference->event_type }}][days_before]" value="{{ $preference->days_before }}">@endif
                            </div>
                        @endforeach
                        @if($canUpdateAdvanced)<button class="btn btn-primary mt-3"><i class="fa-solid fa-floppy-disk me-2"></i>Save Preferences</button>@endif
                        @permission('notifications','view')<a class="btn btn-soft mt-3" href="{{ route('notifications.index') }}#alertRules">Advanced Rules</a>@endpermission
                    </div>
                </form>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="security">
                <div class="panel-header"><h2>Security Settings</h2></div>
                <div class="panel-body"><form method="POST" action="{{ route('settings.security.update') }}">@csrf
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Session Timeout *</label><select class="form-select" name="session_timeout">@foreach([15,30,60,120] as $minutes)<option value="{{ $minutes }}" @selected($settings->session_timeout===$minutes)>{{ $minutes < 60 ? $minutes.' minutes' : ($minutes/60).' hour'.($minutes>60?'s':'') }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Password Expiry</label><select class="form-select" name="password_expiry_days"><option value="" @selected($settings->password_expiry_days===null)>Never</option>@foreach([30,60,90,180] as $days)<option value="{{ $days }}" @selected($settings->password_expiry_days===$days)>{{ $days }} days</option>@endforeach</select></div>
                    </div>
                    @foreach([['require_mfa','Require multi-factor authentication',$settings->require_mfa],['strong_password','Enforce strong password policy',$settings->strong_password],['restrict_concurrent_sessions','Restrict concurrent sessions',$settings->restrict_concurrent_sessions]] as [$name,$label,$enabled])
                        <label class="security-setting-row"><span><strong>{{ $label }}</strong><small>Policy is stored centrally for all managed users</small></span><span class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" @checked($enabled)></span></label>
                    @endforeach
                    @if($canUpdateAdvanced)<button class="btn btn-primary mt-4"><i class="fa-solid fa-shield-halved me-2"></i>Update Security Policy</button>@endif
                </form></div>
            </div>
            @endif

            @if($canViewBasic)
            <div class="tab-pane fade {{ $activeTab === 'theme' ? 'show active' : '' }}" id="theme">
                <div class="panel-header"><h2>Theme Settings</h2></div>
                <div class="panel-body"><p class="text-secondary">Set the application default. A user's top-bar theme choice can still override it locally.</p>
                    <form method="POST" action="{{ route('settings.theme.update') }}" id="themeSettingsForm">@csrf
                        <div class="theme-choice-grid">
                            @foreach([['light','fa-sun','Light','Clean light interface'],['dark','fa-moon','Dark','Low-light dark interface'],['system','fa-display','System','Follow device preference']] as [$value,$icon,$label,$description])
                                <label class="theme-choice"><input type="radio" name="default_theme" value="{{ $value }}" @checked($settings->default_theme===$value)><span><i class="fa-solid {{ $icon }}"></i><strong>{{ $label }}</strong><small>{{ $description }}</small></span></label>
                            @endforeach
                        </div>
                        @if($canUpdateBasic)<button class="btn btn-primary mt-4"><i class="fa-solid fa-palette me-2"></i>Save Default Theme</button>@endif
                    </form>
                </div>
            </div>
            @endif
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(()=>{ 
 const readOnlyGroups={
  basic:@json(!$canUpdateBasic),
  advanced:@json(!$canUpdateAdvanced)
 };
 if(readOnlyGroups.basic)document.querySelectorAll('#general form input,#general form select,#general form textarea,#company form input,#company form select,#company form textarea,#theme form input,#theme form select,#theme form textarea').forEach(field=>field.disabled=true);
 if(readOnlyGroups.advanced)document.querySelectorAll('#smtp form input,#smtp form select,#smtp form textarea,#notifications form input,#notifications form select,#notifications form textarea,#security form input,#security form select,#security form textarea').forEach(field=>field.disabled=true);
 const form=document.getElementById('themeSettingsForm');
 const resolve=theme=>theme==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):theme;
 form?.querySelectorAll('input[name="default_theme"]').forEach(input=>input.addEventListener('change',()=>{document.documentElement.dataset.theme=resolve(input.value);window.dispatchEvent(new CustomEvent('themechange'))}));
 form?.addEventListener('submit',()=>{const theme=form.querySelector('input[name="default_theme"]:checked')?.value||'light';localStorage.setItem('IIM-theme',theme)});
})();
</script>
@endpush
