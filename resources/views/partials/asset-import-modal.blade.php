<div class="modal fade" id="assetImportModal" tabindex="-1" aria-labelledby="assetImportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('assets.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="assetImportModalLabel">Import Assets (Excel / CSV)</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <a class="btn btn-soft btn-sm" href="{{ route('assets.import-sample') }}">
              <i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample (Format)
            </a>

          </div>

          <div class="mb-2">
            <label class="form-label">Excel or CSV file</label>
            <input class="form-control" type="file" name="csv" accept=".xlsx,.xls,.csv,.txt" required>
            <small class="text-secondary">Note: Asset Tags are generated automatically during import. Please use the sample file and keep the column names and order unchanged.</small>
            @foreach($errors->assetImport->all() as $message)
              <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
            @endforeach
          </div>

          @if($errorsList = session('importErrors'))
            @if(count($errorsList))
              <div class="alert alert-danger mt-3">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please correct these import errors</div>
                @foreach($errorsList as $error)
                  <div class="{{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                    @if($error['row'])
                      <strong>Excel row {{ $error['row'] }}: {{ $error['asset'] }}</strong>
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
