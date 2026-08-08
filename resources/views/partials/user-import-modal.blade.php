<div class="modal fade" id="userImportModal" tabindex="-1" aria-labelledby="userImportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="userImportModalLabel">Import Faculties (Excel / CSV)</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <a class="btn btn-soft btn-sm" href="{{ route('users.import-sample') }}">
              <i class="fa-solid fa-file-arrow-down me-2"></i>Download Sample (Format)
            </a>
          </div>

          <div class="mb-2">
            <label class="form-label">Excel or CSV file</label>
            <input class="form-control" type="file" name="csv" accept=".xlsx,.xls,.csv,.txt" required>
            <small class="text-secondary">Note: Faculty IDs are generated automatically. Imported faculties are login-enabled and Active by default. Please use the sample file and keep the column names and order unchanged.</small>
            @foreach($errors->userImport->all() as $message)
              <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $message }}</div>
            @endforeach
          </div>

          @if(($result = session('userImportResult')) && ($result['inserted'] ?? 0) > 0)
            <div class="alert alert-success mt-3">
              <i class="fa-solid fa-circle-check me-2"></i>{{ $result['inserted'] }} faculty(s) imported successfully.
            </div>
          @endif

          @if($errorsList = session('userImportErrors'))
            @if(count($errorsList))
              <div class="alert alert-danger mt-3">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Please correct these import errors</div>
                @foreach($errorsList as $error)
                  <div class="{{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                    @if($error['row'])
                      <strong>Excel row {{ $error['row'] }}: {{ $error['user'] }}</strong>
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
