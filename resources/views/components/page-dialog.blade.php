{{--
    One shared dialog that shows another page of the system (Edit student, Add term, ...).
    Any link with data-dialog opens its page here; see resources/js/page-dialog.js.
    It does not close on an outside click or Escape, so typed information is not lost by accident.
--}}
<div class="modal fade" id="pageDialog" tabindex="-1" aria-labelledby="pageDialogTitle" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" data-page-dialog-size>
        <div class="modal-content">
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title" id="pageDialogTitle" data-page-dialog-title>Loading…</h2>
                    <div class="small text-secondary" data-page-dialog-subtitle></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="page-dialog-body">
                <div class="page-dialog-loading" data-page-dialog-loading>
                    <span class="skeleton skeleton-field"></span>
                    <span class="skeleton skeleton-field w-75"></span>
                    <span class="skeleton skeleton-field w-50"></span>
                </div>
                <iframe class="page-dialog-frame" title="Dialog content" data-page-dialog-frame></iframe>
            </div>
        </div>
    </div>
</div>
