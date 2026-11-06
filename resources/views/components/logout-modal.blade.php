<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-3">
            <div class="modal-body">
                <h2 class="h5 fw-bold" id="logoutModalLabel">Log out of SIES?</h2>
                <p class="text-secondary small">You will need to sign in again to access your workspace.</p>
                <form method="POST" action="{{ route('logout') }}" class="d-flex gap-2 justify-content-center">
                    @csrf
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-3">Yes, log out</button>
                </form>
            </div>
        </div>
    </div>
</div>
