{{-- One shared confirmation dialog; buttons with data-confirm-* fill it in (resources/js/app.js). --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-describedby="confirmModalText" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-2">
            <div class="modal-body text-center">
                <div class="dialog-icon is-warning" data-confirm-icon aria-hidden="true"><x-icon name="alert" :size="22" /></div>
                <h2 class="h5 fw-bold mb-2" id="confirmModalLabel" data-confirm-title>Are you sure?</h2>
                <p class="text-secondary small mb-4" id="confirmModalText" data-confirm-message></p>
                <form method="POST" action="#" class="d-flex gap-2 justify-content-center">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy flex-fill" data-confirm-submit>Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>
