@php
    $activeTypes = $types->where('status', 'Active')->values();
    $csvClass = $csvClass ?? \App\Support\AssetCsv::class;
@endphp

<div class="asset-type-checklist" data-prefix="{{ $prefix }}">
    <div class="asset-type-checklist-head">
        <label class="form-label mb-0">Asset Types</label>
        <div class="asset-type-checklist-toggle">
            <button type="button" class="link-btn" data-check-all>Select all</button>
            <span>&middot;</span>
            <button type="button" class="link-btn" data-check-none>Select none</button>
        </div>
    </div>

    <div class="asset-type-checklist-grid">
        @forelse($activeTypes as $type)
            <label class="asset-type-option">
                <input type="checkbox" name="types[]" value="{{ $type->id }}" checked>
                <span>{{ $type->name }}</span>
            </label>
        @empty
            <p class="text-secondary small mb-0">No active asset types yet — add one under Asset Management first.</p>
        @endforelse
    </div>

    @if($activeTypes->isNotEmpty())
        <details class="asset-type-preview">
            <summary>Preview sheet columns per type</summary>
            @foreach($activeTypes as $type)
                @php
                    // Asset workbooks (export and sample) also carry the type's
                    // Parameter columns and timestamps, plus Asset Tag on export —
                    // preview exactly what the downloaded sheet will contain.
                    $columns = $csvClass === \App\Support\AssetCsv::class
                        ? $csvClass::columnSpec($type, withAssetTag: $prefix === 'export', forExport: true)
                        : $csvClass::columnSpec($type);
                @endphp
                <div class="asset-type-preview-row">
                    <strong>{{ $type->name }}</strong>
                    <div class="asset-import-columns">
                        @foreach($columns as $column)
                            <span class="asset-import-chip {{ isset($column['parameter']) ? 'dynamic' : '' }}">{{ $column['label'] }}{{ isset($column['parameter']) ? ' (parameter)' : ($column['required'] ? '' : ' (optional)') }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </details>
    @endif
</div>
