<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-3">
            <div class="modal-body text-center">
                <h2 class="h5 fw-bold" id="confirmModalLabel" data-confirm-title>Are you sure?</h2>
                <p class="text-secondary small" data-confirm-message></p>
                <form method="POST" action="#" class="d-flex gap-2 justify-content-center">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4" data-confirm-submit>Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>
