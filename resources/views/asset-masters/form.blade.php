@php
    $editing = isset($record) && $record;
    $modalId = $editing ? 'editMasterModal'.$record->id : 'createMasterModal';
    $action = $editing ? route('asset-management.'.$module.'.update', $record->id) : route('asset-management.'.$module.'.store');
    $value = fn ($field) => old($field, $editing ? $record->{$field} : '');
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ in_array($module, ['types', 'sub-types']) ? 'modal-lg' : '' }}"><div class="modal-content panel">
        <div class="modal-header" style="border-color:var(--border-color)"><h2 class="modal-title fs-6">{{ $editing ? 'Edit' : 'Add' }} {{ $singular }}</h2><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">@csrf @if($editing) @method('PUT') @endif
            <div class="modal-body p-4"><div class="row g-3 g-md-4">
                <div class="{{ $module === 'sub-types' ? 'col-12 col-sm-6 col-md-3' : 'col-md-7' }}"><label class="form-label">Name <span class="text-danger">*</span></label><input class="form-control" name="name" value="{{ $value('name') }}" placeholder="Enter {{ strtolower($singular) }} name" required></div>
                <div class="{{ $module === 'sub-types' ? 'col-12 col-sm-6 col-md-3' : 'col-md-5' }}"><label class="form-label">Code</label><input class="form-control" value="{{ $editing ? $record->code : $prefix.'-Auto' }}" readonly><small class="text-secondary">Generated automatically</small></div>
                @if($module === 'sub-departments')
                    <div class="col-12"><label class="form-label">Department <span class="text-danger">*</span></label><select class="form-select" name="department_id" required><option value="">Select department</option>@foreach($options['department_id'] as $id=>$label)<option value="{{ $id }}" @selected((string)$value('department_id') === (string)$id)>{{ $label }}</option>@endforeach</select></div>
                @elseif($module === 'sub-types')
                    <div class="col-12 col-sm-6 col-md-3"><label class="form-label">Asset Type <span class="text-danger">*</span></label><select class="form-select" name="asset_type_id" data-subtype-type-select required><option value="">Select asset type</option>@foreach($options['asset_type_id'] as $id=>$label)<option value="{{ $id }}" @selected((string)$value('asset_type_id') === (string)$id)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-12 col-sm-6 col-md-3"><label class="form-label">Brand <span class="text-danger">*</span></label><select class="form-select" name="brand_id" required><option value="">Select brand</option>@foreach($options['brand_id'] as $id=>$label)<option value="{{ $id }}" @selected((string)$value('brand_id') === (string)$id)>{{ $label }}</option>@endforeach</select></div>

                    <div class="col-12"><hr class="my-1" style="border-color:var(--border-color);opacity:.6"></div>

                    <div class="col-12">
                        <div class="d-flex align-items-center gap-2 p-3 border rounded-3" style="background:var(--bg-secondary)">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_required" value="1" id="subtypeIsRequired{{ $modalId }}" @checked((bool) old('is_required', $editing ? $record->is_required : true))>
                            <label class="form-check-label mb-0" for="subtypeIsRequired{{ $modalId }}">Required field in asset forms and imports</label>
                        </div>
                    </div>

                    <div class="col-12" data-subtype-parameters-section data-existing="{{ json_encode($editing ? ($record->parameter_values ?? []) : old('parameter_values', [])) }}">
                        <div class="p-3 border rounded-3" style="background:var(--bg-secondary)">
                            <label class="form-label mb-0">Parameters</label>
                            <div class="params-grid" data-subtype-parameters-list></div>
                            <div class="text-secondary small d-flex align-items-center gap-1 mt-2" data-subtype-parameters-empty><i class="fa-regular fa-circle-question"></i><span>Select an Asset Type that has configured parameters to enter values here.</span></div>
                        </div>
                    </div>
                @elseif($module === 'brands')
                    <div class="col-md-6"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ $value('country') }}" placeholder="Enter country name"></div>
                    <div class="col-md-6"><label class="form-label">Support Contact</label><input class="form-control" name="support_contact" value="{{ $value('support_contact') }}" placeholder="Enter email or phone number"></div>
                    <div class="col-12"><label class="form-label">Brand Logo {{ $editing ? '(leave blank to keep current)' : '' }}</label><input class="form-control" type="file" name="logo" data-max-size-mb="2" accept=".jpg,.jpeg,.png,.webp,image/*"><small class="text-secondary">JPG, PNG or WebP up to 2 MB</small></div>
                @endif
                @if($module === 'types')
                    @php $existingParameters = $editing ? ($record->parameters ?? []) : old('parameters', []); @endphp
                    <div class="col-12" data-parameters-section>
                        <div class="p-3 border rounded-3" style="background:var(--bg-secondary)">
                            <label class="form-label mb-2">Parameters</label>
                            <div data-parameters-list>
                                @foreach($existingParameters as $parameter)
                                    <div class="input-group mb-2" data-parameter-row>
                                        <input class="form-control" name="parameters[]" value="{{ $parameter }}" placeholder="Parameter name (e.g. RAM)">
                                        <button type="button" class="btn btn-outline-danger" data-remove-parameter><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-soft btn-sm mt-1" data-add-parameter><i class="fa-solid fa-plus me-1"></i>Add Parameter</button>
                            <small class="text-secondary d-block mt-2">Define the parameter names Subtypes of this Type will provide values for (e.g. RAM, Processor).</small>
                        </div>
                    </div>
                @endif
                @if(in_array($module, ['departments','sub-departments','types','sub-types','categories']))
                    <div class="col-12"><hr class="my-1" style="border-color:var(--border-color);opacity:.6"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Enter a short description">{{ $value('description') }}</textarea></div>
                @endif
                <div class="col-12"><label class="form-label">Status <span class="text-danger">*</span></label><select class="form-select" name="status" required><option value="" disabled @selected($value('status') === '')>Select status</option><option @selected($value('status') === 'Active')>Active</option><option @selected($value('status') === 'Inactive')>Inactive</option></select></div>
            </div></div>
            <div class="modal-footer" style="border-color:var(--border-color)"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">{{ $editing ? 'Save Changes' : 'Create '.$singular }}</button></div>
        </form>
    </div></div>
</div>
