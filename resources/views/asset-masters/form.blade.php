@php
    $editing = isset($record) && $record;
    $modalId = $editing ? 'editMasterModal'.$record->id : 'createMasterModal';
    $action = $editing ? route('asset-management.'.$module.'.update', $record->id) : route('asset-management.'.$module.'.store');
    $value = fn ($field) => old($field, $editing ? $record->{$field} : '');
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content panel">
        <div class="modal-header" style="border-color:var(--border-color)"><h2 class="modal-title fs-6">{{ $editing ? 'Edit' : 'Add' }} {{ $singular }}</h2><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">@csrf @if($editing) @method('PUT') @endif
            <div class="modal-body p-4"><div class="row g-3">
                <div class="col-md-7"><label class="form-label">Name *</label><input class="form-control" name="name" value="{{ $value('name') }}" placeholder="Enter {{ strtolower($singular) }} name" required></div>
                <div class="col-md-5"><label class="form-label">Code</label><input class="form-control" value="{{ $editing ? $record->code : $prefix.'-Auto' }}" readonly><small class="text-secondary">Generated automatically</small></div>
                @if($module === 'sub-departments')
                    <div class="col-12"><label class="form-label">Department *</label><select class="form-select" name="department_id" required><option value="">Select department</option>@foreach($options['department_id'] as $id=>$label)<option value="{{ $id }}" @selected((string)$value('department_id') === (string)$id)>{{ $label }}</option>@endforeach</select></div>
                @elseif($module === 'brands')
                    <div class="col-md-6"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ $value('country') }}" placeholder="Enter country name"></div>
                    <div class="col-md-6"><label class="form-label">Support Contact</label><input class="form-control" name="support_contact" value="{{ $value('support_contact') }}" placeholder="Enter email or phone number"></div>
                    <div class="col-12"><label class="form-label">Brand Logo {{ $editing ? '(leave blank to keep current)' : '' }}</label><input class="form-control" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/*"><small class="text-secondary">JPG, PNG or WebP up to 2 MB</small></div>
                @endif
                @if(in_array($module, ['departments','sub-departments','types','categories']))
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Enter a short description">{{ $value('description') }}</textarea></div>
                @endif
                <div class="col-12"><label class="form-label">Status *</label><select class="form-select" name="status" required><option value="" disabled @selected($value('status') === '')>Select status</option><option @selected($value('status') === 'Active')>Active</option><option @selected($value('status') === 'Inactive')>Inactive</option></select></div>
            </div></div>
            <div class="modal-footer" style="border-color:var(--border-color)"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">{{ $editing ? 'Save Changes' : 'Create '.$singular }}</button></div>
        </form>
    </div></div>
</div>
