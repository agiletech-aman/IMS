@php
$editing = isset($asset);
$field = fn ($name, $default = '') => old($name, $editing ? $asset->{$name} : $default);
$warrantyEnabled = (bool) old('warranty_enabled', $editing && filled($asset->warranty_expiry));
$amcEnabled = (bool) old('amc_enabled', $editing && filled($asset->amc_expiry));
@endphp
<style>
.combo-select{position:relative}
.combo-menu{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:60;max-height:220px;overflow-y:auto;background:var(--card-bg);border:1px solid var(--border-color);border-radius:9px;box-shadow:var(--shadow)}
.combo-option{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;font-size:13px;cursor:pointer}
.combo-option:hover{background:var(--bg-secondary)}
.combo-option small{color:var(--text-muted);font-size:10px}
</style>
<form method="POST" action="{{ $editing ? route('assets.update',$asset) : route('assets.store') }}" enctype="multipart/form-data">
    @csrf @if($editing) @method('PUT') @endif
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Asset Information</h2>
                <p>Identification, assignment and lifecycle details</p>
            </div><span class="badge-soft">Required fields <span class="text-danger">*</span></span>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4"><label class="form-label">Asset ID</label><input class="form-control" value="{{ $editing ? $asset->asset_tag : 'AST-XX-001' }}" readonly><small class="text-secondary">Uses asset name's first and last character</small></div>
<div class="col-md-6 col-xl-4"><label class="form-label">Asset Name <span class="text-danger">*</span></label><input class="form-control" name="name" value="{{ $field('name') }}" placeholder="Enter asset name" required></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Type <span class="text-danger">*</span></label><select class="form-select" name="asset_type_id" id="assetType" required>
                        <option value="">Select type</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)$field('asset_type_id')===(string)$type->id)>{{ $type->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Subtype</label><select class="form-select" id="assetSubtype" name="asset_subtype_id">
                        <option value="">Select subtype</option>@foreach($subtypes as $subtype)<option value="{{ $subtype->id }}" data-asset-type="{{ $subtype->asset_type_id }}" @selected((string)$field('asset_subtype_id')===(string)$subtype->id)>{{ $subtype->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Serial Number <span class="text-danger">*</span></label><input class="form-control" name="serial_number" value="{{ $field('serial_number') }}" placeholder="Enter unique serial number" required></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">FR Number <span class="text-danger">*</span></label><input class="form-control" name="fr_number" value="{{ $field('fr_number') }}" placeholder="Enter FR number" required></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Department <span class="text-danger">*</span></label><select class="form-select" name="department_id" id="assetDepartment" required>
                        <option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)$field('department_id')===(string)$department->id)>{{ $department->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Sub Department <span class="text-danger">*</span></label><select class="form-select" name="sub_department_id" id="assetSubDepartment" required>
                        <option value="">Select sub department</option>@foreach($subDepartments as $subDepartment)<option value="{{ $subDepartment->id }}" data-department="{{ $subDepartment->department_id }}" @selected((string)$field('sub_department_id')===(string)$subDepartment->id)>{{ $subDepartment->name }}</option>@endforeach
                    </select></div>
<div class="col-md-6 col-xl-4"><label class="form-label">Installation Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="installation_date" value="{{ old('installation_date', $editing ? $asset->installation_date?->format('Y-m-d') : '') }}" placeholder="Select installation date" required></div>
                <div class="col-12" id="subtypeFieldsWrap" hidden>
                    <hr class="my-1">
                    <p class="form-label mb-2">Subtype Parameters</p>
                    <div class="row g-3" id="subtypeFieldsContainer"></div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label">Assigned To</label>
                    <div class="combo-select">
                        <input type="text" class="form-control" id="assignedToSearch" name="assigned_to" autocomplete="off" placeholder="Search users..." value="{{ $field('assigned_to') }}">
                        <div class="combo-menu" id="assignedToMenu" hidden>
                            <div class="combo-option" data-value="">Unassigned</div>
                            @foreach($users as $user)
                                <div class="combo-option" data-value="{{ $user->name }}"><span>{{ $user->name }}</span><small>{{ $user->unique_id }}</small></div>
                            @endforeach
                        </div>
                    </div>
                    <small class="text-secondary">Search and pick from the user directory</small>
                </div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Status <span class="text-danger">*</span></label><select class="form-select" name="status" required>
                        <option value="" disabled @selected($field('status')==='' )>Select asset status</option>@foreach(['Active','In Stock','Under Maintenance','Retired'] as $status)<option @selected($field('status')===$status)>{{ $status }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Asset Image {{ $editing ? '(leave blank to keep current)' : '' }}</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/*"><small class="text-secondary">JPG, PNG or WebP up to 4 MB</small></div>
                @if($editing && $asset->image_path)<div class="col-12"><img src="{{ \App\Support\PublicUrl::storage($asset->image_path) }}" alt="Current asset image" style="width:120px;height:90px;object-fit:cover;border-radius:10px"></div>@endif
                <div class="col-12">
                    <section class="asset-coverage">
                        <div class="asset-coverage-heading">
                            <div>
                                <span class="coverage-heading-icon"><i class="fa-solid fa-shield-halved"></i></span>
                                <span><strong>Warranty & AMC</strong><small>Optional — add coverage only when it applies to this asset.</small></span>
                            </div>
                            <span class="badge-soft muted">Optional</span>
                        </div>
                        <div class="coverage-grid">
                            <div class="coverage-option {{ $warrantyEnabled ? 'is-enabled' : '' }}" data-coverage-card>
                                <div class="coverage-option-top">
                                    <span class="coverage-option-icon warranty"><i class="fa-solid fa-certificate"></i></span>
                                    <span class="coverage-option-copy"><strong>Warranty</strong><small>Track manufacturer warranty</small></span>
                                    <label class="coverage-switch">
                                        <input type="hidden" name="warranty_enabled" value="0">
                                        <input type="checkbox" name="warranty_enabled" value="1" data-coverage-toggle="warrantyFields" @checked($warrantyEnabled)>
                                        <span aria-hidden="true"></span>
                                        <em>{{ $warrantyEnabled ? 'Added' : 'Add' }}</em>
                                    </label>
                                </div>
                                <div class="coverage-fields" id="warrantyFields" @if(!$warrantyEnabled) hidden @endif>
                                    <label class="form-label" for="warrantyExpiry">Warranty Expiry Date</label>
                                    <input class="form-control" id="warrantyExpiry" type="date" name="warranty_expiry" value="{{ old('warranty_expiry', $editing ? $asset->warranty_expiry?->format('Y-m-d') : '') }}">
                                </div>
                            </div>

                            <div class="coverage-option {{ $amcEnabled ? 'is-enabled' : '' }}" data-coverage-card>
                                <div class="coverage-option-top">
                                    <span class="coverage-option-icon amc"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                                    <span class="coverage-option-copy"><strong>AMC</strong><small>Track annual maintenance contract</small></span>
                                    <label class="coverage-switch">
                                        <input type="hidden" name="amc_enabled" value="0">
                                        <input type="checkbox" name="amc_enabled" value="1" data-coverage-toggle="amcFields" @checked($amcEnabled)>
                                        <span aria-hidden="true"></span>
                                        <em>{{ $amcEnabled ? 'Added' : 'Add' }}</em>
                                    </label>
                                </div>
                                <div class="coverage-fields" id="amcFields" @if(!$amcEnabled) hidden @endif>
                                    <label class="form-label" for="amcExpiry">AMC Expiry Date</label>
                                    <input class="form-control" id="amcExpiry" type="date" name="amc_expiry" value="{{ old('amc_expiry', $editing ? $asset->amc_expiry?->format('Y-m-d') : '') }}">
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="notes" rows="3" placeholder="Add configuration, installation or handover details">{{ $field('notes') }}</textarea></div>
            </div>
        </div>
        <div class="panel-header justify-content-end"><a href="{{ route('assets.index') }}" class="btn btn-soft">Cancel</a><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>{{ $editing?'Save Changes':'Create Asset' }}</button></div>
    </div>
</form>

@push('scripts')<script>
    document.addEventListener('DOMContentLoaded', () => {
        const filter = (parentId, childId, key) => {
            const parent = document.getElementById(parentId),
                child = document.getElementById(childId);
            if (!parent || !child) return;
            const sync = () => {
                [...child.options].forEach((option, index) => {
                    if (index) option.hidden = option.dataset[key] !== parent.value
                });
                if (child.selectedOptions[0]?.hidden) child.value = ''
            };
            parent.addEventListener('change', sync);
            sync()
        };
filter('assetDepartment', 'assetSubDepartment', 'department');

        @php
            $subtypesJson = $subtypes->map(function ($subtype) {
                return [
                    'id' => $subtype->id,
                    'asset_type_id' => $subtype->asset_type_id,
                    'name' => $subtype->name,
                    'parameter_values' => $subtype->parameter_values ?: [],
                ];
            });
        @endphp
        const subtypesData = @json($subtypesJson);
        const typeParameters = @json($types->pluck('parameters', 'id'));
        const assetTypeSelect = document.getElementById('assetType');
        const assetSubtypeSelect = document.getElementById('assetSubtype');
        const subtypeWrap = document.getElementById('subtypeFieldsWrap');
        const subtypeContainer = document.getElementById('subtypeFieldsContainer');

        filter('assetType', 'assetSubtype', 'assetType');

        const renderSubtypeParameters = () => {
            if (!assetTypeSelect || !assetSubtypeSelect || !subtypeContainer) return;

            subtypeContainer.innerHTML = '';

            const params = typeParameters[assetTypeSelect.value] || [];
            const subtype = subtypesData.find(s => String(s.id) === assetSubtypeSelect.value);

            subtypeWrap.hidden = params.length === 0 || !subtype;

            if (!subtype) return;

            params.forEach(param => {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-xl-4';

                const label = document.createElement('label');
                label.className = 'form-label';
                label.textContent = param;

                const value = document.createElement('div');
                value.className = 'form-control-plaintext fw-semibold';
                value.textContent = subtype.parameter_values[param] || '—';

                col.append(label, value);
                subtypeContainer.append(col);
            });
        };

        assetTypeSelect?.addEventListener('change', renderSubtypeParameters);
        assetSubtypeSelect?.addEventListener('change', renderSubtypeParameters);
        renderSubtypeParameters();

        const assignedSearch = document.getElementById('assignedToSearch');
        const assignedMenu = document.getElementById('assignedToMenu');
        if (assignedSearch && assignedMenu) {
            const options = [...assignedMenu.querySelectorAll('.combo-option')];

            const filterOptions = () => {
                const term = assignedSearch.value.trim().toLowerCase();
                let visibleCount = 0;
                options.forEach(option => {
                    const match = option.dataset.value === '' || option.dataset.value.toLowerCase().includes(term);
                    option.hidden = !match;
                    if (match) visibleCount++;
                });
                assignedMenu.hidden = visibleCount === 0;
            };

            assignedSearch.addEventListener('input', filterOptions);
            assignedSearch.addEventListener('focus', filterOptions);

            options.forEach(option => {
                option.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    assignedSearch.value = option.dataset.value;
                    assignedMenu.hidden = true;
                });
            });

            assignedSearch.addEventListener('blur', () => {
                setTimeout(() => { assignedMenu.hidden = true; }, 150);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') assignedMenu.hidden = true;
            });
        }

        document.querySelectorAll('[data-coverage-toggle]').forEach(toggle => {
            const fields = document.getElementById(toggle.dataset.coverageToggle);
            const card = toggle.closest('[data-coverage-card]');
            const label = toggle.closest('.coverage-switch')?.querySelector('em');
            const dateInput = fields?.querySelector('input[type="date"]');
            const sync = (clearValue = false) => {
                fields.hidden = !toggle.checked;
                card?.classList.toggle('is-enabled', toggle.checked);
                if (label) label.textContent = toggle.checked ? 'Added' : 'Add';
                if (!toggle.checked && clearValue && dateInput) dateInput.value = '';
            };
            toggle.addEventListener('change', () => sync(true));
            sync();
        });
    });
</script>@endpush
