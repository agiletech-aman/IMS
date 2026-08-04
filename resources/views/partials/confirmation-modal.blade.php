<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content panel">
            <div class="modal-body p-4 text-center">
                <span id="confirmationModalIcon" class="stat-icon danger mx-auto mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <h2 class="fs-5 mb-2" id="confirmationModalTitle">Confirm action</h2>
                <p class="text-secondary mb-0" id="confirmationModalMessage">Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer justify-content-center" style="border-color:var(--border-color)">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmationModalConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>
