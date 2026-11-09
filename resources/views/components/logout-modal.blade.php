<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-2">
            <div class="modal-body">
                <div class="dialog-icon is-info" aria-hidden="true"><x-icon name="logout" :size="22" /></div>
                <h2 class="h5 fw-bold mb-2" id="logoutModalLabel">Log out of SIES?</h2>
                <p class="text-secondary small mb-4">You will need to sign in again to access your workspace.</p>
                <form method="POST" action="{{ route('logout') }}" class="d-flex gap-2 justify-content-center">
                    @csrf
                    <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy flex-fill">Yes, log out</button>
                </form>
            </div>
        </div>
    </div>
</div>
