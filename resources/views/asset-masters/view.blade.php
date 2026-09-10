<?php /** @var \App\Models\AssetType|\App\Models\AssetSubtype $record */ ?>
<div class="modal fade" id="viewMasterModal{{ $record->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content panel">
        <div class="modal-header" style="border-color:var(--border-color)"><h2 class="modal-title fs-6">{{ $singular }} Details</h2><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-4">
            <div class="detail-list">
                <div class="detail-item"><span>Name</span><strong>{{ $record->name }}</strong></div>
                <div class="detail-item"><span>Code</span><strong>{{ $record->code }}</strong></div>
                @if($module === 'sub-types')
                    <div class="detail-item"><span>Asset Type</span><strong>{{ $record->assetType?->name ?: '—' }}</strong></div>
                    <div class="detail-item"><span>Brand</span><strong>{{ $record->brand?->name ?: '—' }}</strong></div>
                    <div class="detail-item"><span>Required in asset forms</span><strong>{{ $record->is_required ? 'Yes' : 'No' }}</strong></div>
                @endif
                <div class="detail-item"><span>Status</span><strong><span class="badge-soft {{ $record->status === 'Active' ? 'success' : 'muted' }}">{{ $record->status }}</span></strong></div>
                <div class="detail-item"><span>Description</span><strong>{{ $record->description ?: '—' }}</strong></div>
            </div>

            @if($module === 'types')
                <div class="mt-3">
                    <label class="form-label mb-2">Parameters</label>
                    @if(!empty($record->parameters))
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($record->parameters as $parameter)
                                <span class="badge-soft">{{ $parameter }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="text-secondary small p-3 border rounded-3" style="background:var(--bg-secondary)">No parameters configured for this type.</div>
                    @endif
                </div>
            @elseif($module === 'sub-types')
                <div class="mt-3">
                    <label class="form-label mb-2">Parameter Values</label>
                    @php
                        $paramLabels = $record->assetType?->parameters ?: array_keys($record->parameter_values ?? []);
                        $paramValues = $record->parameter_values ?? [];
                    @endphp
                    @if(!empty($paramLabels))
                        <div class="detail-list">
                            @foreach($paramLabels as $parameter)
                                <div class="detail-item"><span>{{ $parameter }}</span><strong>{{ $paramValues[$parameter] ?? '—' ?: '—' }}</strong></div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-secondary small p-3 border rounded-3" style="background:var(--bg-secondary)">This subtype's Asset Type has no configured parameters.</div>
                    @endif
                </div>
            @endif
        </div>
        <div class="modal-footer" style="border-color:var(--border-color)"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
