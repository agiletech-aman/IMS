@php
    $editing = isset($vendor) && $vendor;
    $modalId = $editing ? 'editVendorModal'.$vendor->id : 'createVendorModal';
    $action = $editing ? route('vendors.update', $vendor) : route('vendors.store');
    $value = fn (string $field, mixed $default = '') => old($field, $editing ? $vendor->{$field} : $default);
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content panel">
            <div class="modal-header" style="border-color:var(--border-color)">
                <div>
                    <h2 class="modal-title fs-6">{{ $editing ? 'Edit' : 'Add' }} Vendor / OEM</h2>
                    <p class="small text-secondary mb-0 mt-1">Contact, classification and service agreement details</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ $action }}">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="name" value="{{ $value('name') }}" placeholder="Enter vendor or OEM name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vendor Code</label>
                            <input class="form-control" value="{{ $editing ? $vendor->code : 'VN-Auto' }}" readonly>
                            <small class="text-secondary">Generated automatically</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="vendor_type" required>
                                <option value="">Select type</option>
                                @foreach(['Vendor','OEM','Vendor & OEM'] as $type)
                                    <option @selected($value('vendor_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <input class="form-control" name="category" value="{{ $value('category') }}" placeholder="Hardware, Network, CCTV…">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                @foreach(['Active','Inactive'] as $status)
                                    <option @selected($value('status', 'Active') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><div class="form-section-label">Primary Contact</div></div>
                        <div class="col-md-4">
                            <label class="form-label">Contact Person</label>
                            <input class="form-control" name="contact_person" value="{{ $value('contact_person') }}" placeholder="Full name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="phone" value="{{ $value('phone') }}" placeholder="+91 98765 43210">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" value="{{ $value('email') }}" placeholder="contact@company.com">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Website</label>
                            <input class="form-control" type="url" name="website" value="{{ $value('website') }}" placeholder="https://company.com">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Address</label>
                            <input class="form-control" name="address" value="{{ $value('address') }}" placeholder="Office address">
                        </div>

                        <div class="col-12"><div class="form-section-label">Contract & Performance</div></div>
                        <div class="col-md-4">
                            <label class="form-label">AMC / Contract Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="amc_status" required>
                                @foreach(['Not Applicable','Active','Renewal Due','Expired'] as $amcStatus)
                                    <option @selected($value('amc_status', 'Not Applicable') === $amcStatus)>{{ $amcStatus }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contract Start</label>
                            <input class="form-control" type="date" name="contract_start" value="{{ old('contract_start', $editing ? $vendor->contract_start?->format('Y-m-d') : '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contract End / Renewal</label>
                            <input class="form-control" type="date" name="contract_end" value="{{ old('contract_end', $editing ? $vendor->contract_end?->format('Y-m-d') : '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rating</label>
                            <input class="form-control" type="number" name="rating" min="0" max="5" step="0.1" value="{{ $value('rating') }}" placeholder="0.0 – 5.0">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <label class="vendor-preferred-option">
                                <input type="hidden" name="preferred" value="0">
                                <input type="checkbox" name="preferred" value="1" @checked((bool) $value('preferred', false))>
                                <span><strong>Preferred Vendor / OEM</strong><small>Mark as a preferred procurement or service partner</small></span>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add service scope, escalation or commercial notes">{{ $value('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color:var(--border-color)">
                    <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>{{ $editing ? 'Save Changes' : 'Create Vendor / OEM' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
