<style>
.asset-type-checklist{border:1px solid #e3eaf1;border-radius:12px;background:#f8fafc;padding:14px 15px;margin-bottom:16px}
.asset-type-checklist-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.asset-type-checklist-toggle{display:flex;align-items:center;gap:6px;font-size:12px;color:#94a3b8}
.link-btn{background:none;border:0;padding:0;color:#3f5cc4;font-size:12px;font-weight:600;cursor:pointer}
.link-btn:hover{text-decoration:underline}
.asset-type-checklist-grid{display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:10px}
.asset-type-option{display:flex;align-items:center;gap:7px;font-size:13px;color:#334155;cursor:pointer}
.asset-type-option input{margin:0}
.asset-type-preview{margin-top:12px;padding-top:11px;border-top:1px dashed #d8e2eb}
.asset-type-preview summary{cursor:pointer;font-size:12.5px;font-weight:600;color:#3f5cc4}
.asset-type-preview-row{margin-top:10px}
.asset-type-preview-row strong{display:block;font-size:12.5px;color:#334155;margin-bottom:5px}
.asset-import-drop{padding:22px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center}
.asset-import-drop i{display:block;margin-bottom:8px;font-size:26px;color:#3f5cc4}
.asset-import-columns{display:flex;flex-wrap:wrap;gap:6px}
.asset-import-chip{padding:3px 10px;border:1px solid #d8e2eb;border-radius:999px;background:#fff;color:#3f5cc4;font-size:11.5px;font-family:'SFMono-Regular',Consolas,monospace}
.asset-import-chip.dynamic{border-style:dashed;color:#0f766a}
.asset-import-note{margin:10px 0 0;font-size:12px;color:#64748b}
</style>

@permission('assets','import')

{{-- =========================================================
     DOWNLOAD SAMPLE (choose Asset Types first)
========================================================= --}}
<div class="modal fade" id="assetSampleModal" tabindex="-1" aria-labelledby="assetSampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="GET" action="{{ route('assets.import-sample') }}">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="assetSampleModalLabel">Download Import Sample</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small">Pick the Asset Types you want a sheet for. Each sheet's columns come straight from that type's current Subtype fields — the list updates automatically as those change.</p>
          @include('partials.asset-type-checklist', ['types' => $assetTypes, 'prefix' => 'sample'])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- =========================================================
     IMPORT
========================================================= --}}
<div class="modal fade" id="assetImportModal" tabindex="-1" aria-labelledby="assetImportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('assets.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="assetImportModalLabel">Import Assets (Excel)</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <a class="btn btn-soft btn-sm" href="#" data-bs-toggle="modal" data-bs-target="#assetSampleModal">
              <i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample (choose types)
            </a>
          </div>

          <div class="asset-import-drop">
            <i class="fa-solid fa-file-excel"></i>
            <label for="assetImportFile" class="form-label fw-semibold mb-2">Excel file (.xlsx / .xls)</label>
            <input class="form-control" id="assetImportFile" type="file" name="csv" accept=".xlsx,.xls" required>
          </div>
          <small class="text-secondary d-block mt-2">
            Each sheet's <strong>name must be an Asset Type</strong> (e.g. "Laptop", "Desktop") — that's how rows get matched to the right type and fields. Leave Asset Tag blank to create a new asset (a tag is generated automatically); enter an existing Asset Tag to update that asset instead.
          </small>
          @foreach($errors->assetImport->all() as $message)
            <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
          @endforeach

          @if($errorsList = session('importErrors'))
            @if(count($errorsList))
              <div class="alert alert-danger mt-3">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please correct these import errors</div>
                @foreach($errorsList as $error)
                  <div class="{{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                    @if($error['row'])
                      <strong>
                        @if(!empty($error['sheet']))Sheet "{{ $error['sheet'] }}", @endif
                        row {{ $error['row'] }}@if(!empty($error['asset'])): {{ $error['asset'] }}@endif
                      </strong>
                    @else
                      <strong>File error</strong>
                    @endif
                    <ul class="mb-0 mt-1">
                      @foreach($error['messages'] as $message)
                        <li>{{ $message }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endforeach
              </div>
            @endif
          @endif
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-upload me-2"></i>Import
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endpermission

@permission('assets','export')

{{-- =========================================================
     EXPORT (choose Asset Types first)
========================================================= --}}
<div class="modal fade" id="assetExportModal" tabindex="-1" aria-labelledby="assetExportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="GET" action="{{ route('assets.export') }}">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="assetExportModalLabel">Export Assets</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small">Pick the Asset Types to export. The workbook will have one sheet per type, with only that type's assets and fields.</p>
          @include('partials.asset-type-checklist', ['types' => $assetTypes, 'prefix' => 'export'])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-export me-2"></i>Export</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endpermission

@push('scripts')
<script>
document.querySelectorAll('.asset-type-checklist').forEach(function (block) {
    var boxes = block.querySelectorAll('input[type="checkbox"]');
    var allBtn = block.querySelector('[data-check-all]');
    var noneBtn = block.querySelector('[data-check-none]');
    if (allBtn) allBtn.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); });
    if (noneBtn) noneBtn.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); });
});
</script>
@endpush
