@php
$editing = isset($asset);
$field = fn ($name, $default = '') => old($name, $editing ? $asset->{$name} : $default);
$warrantyEnabled = (bool) old('warranty_enabled', $editing && filled($asset->warranty_expiry));
$amcEnabled = (bool) old('amc_enabled', $editing && filled($asset->amc_expiry));
@endphp
<form method="POST" action="{{ $editing ? route('assets.update',$asset) : route('assets.store') }}" enctype="multipart/form-data">
    @csrf @if($editing) @method('PUT') @endif
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Asset Information</h2>
                <p>Identification, assignment and lifecycle details</p>
            </div><span class="badge-soft">Required fields *</span>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4"><label class="form-label">Asset ID</label><input class="form-control" value="{{ $editing ? $asset->asset_tag : 'AST-XX-001' }}" readonly><small class="text-secondary">Uses asset name's first and last character</small></div>
<div class="col-md-6 col-xl-4"><label class="form-label">Asset Name *</label><input class="form-control" name="name" value="{{ $field('name') }}" placeholder="Enter asset name" required></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Type *</label><select class="form-select" name="asset_type_id" id="assetType" required>
                        <option value="">Select type</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)$field('asset_type_id')===(string)$type->id)>{{ $type->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Brand</label><select class="form-select" name="brand_id">
                        <option value="">Select brand</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)$field('brand_id')===(string)$brand->id)>{{ $brand->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Serial Number</label><input class="form-control" name="serial_number" value="{{ $field('serial_number') }}" placeholder="Enter unique serial number"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">FR Number</label><input class="form-control" name="fr_number" value="{{ $field('fr_number') }}" placeholder="Enter FR number"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Department</label><select class="form-select" name="department_id" id="assetDepartment">
                        <option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)$field('department_id')===(string)$department->id)>{{ $department->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Sub Department</label><select class="form-select" name="sub_department_id" id="assetSubDepartment">
                        <option value="">Select sub department</option>@foreach($subDepartments as $subDepartment)<option value="{{ $subDepartment->id }}" data-department="{{ $subDepartment->department_id }}" @selected((string)$field('sub_department_id')===(string)$subDepartment->id)>{{ $subDepartment->name }}</option>@endforeach
                    </select></div>
<div class="col-md-6 col-xl-4"><label class="form-label">Installation Date</label><input class="form-control" type="date" name="installation_date" value="{{ old('installation_date', $editing ? $asset->installation_date?->format('Y-m-d') : '') }}" placeholder="Select installation date"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">CPU</label><input class="form-control" name="cpu" value="{{ $field('cpu') }}" placeholder="e.g. Intel Core i7-1255U"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">HDD</label><input class="form-control" name="hdd" value="{{ $field('hdd') }}" placeholder="e.g. 512GB SSD"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">RAM</label><input class="form-control" name="ram" value="{{ $field('ram') }}" placeholder="e.g. 16GB"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Operating System</label><input class="form-control" name="operating_system" value="{{ $field('operating_system') }}" placeholder="e.g. Windows 11 Pro"></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Assigned To</label><select class="form-select" name="assigned_to">
                        <option value="">Unassigned</option>@if(filled($field('assigned_to')))<option value="{{ $field('assigned_to') }}" selected>{{ $field('assigned_to') }}</option>@endif
                    </select><small class="text-secondary">User names can be added here later</small></div>
                <div class="col-md-6 col-xl-4"><label class="form-label">Status *</label><select class="form-select" name="status" required>
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
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" placeholder="Add configuration, installation or handover notes">{{ $field('notes') }}</textarea></div>
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
