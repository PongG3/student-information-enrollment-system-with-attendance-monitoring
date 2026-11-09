<div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="reasonModalLabel" data-reason-title>Reason</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="#">
                @csrf
                <div class="modal-body">
                    <p class="text-secondary small" data-reason-message></p>
                    <label for="reasonText" class="form-label fw-semibold small">Reason <span class="text-danger">*</span></label>
                    <textarea id="reasonText" name="reason" rows="3" maxlength="255" minlength="5" required class="form-control"></textarea>
                    <div class="form-text">At least 5 characters. This is saved with the request and in the audit log.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4" data-reason-submit>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
