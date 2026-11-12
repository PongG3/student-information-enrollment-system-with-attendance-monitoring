{{--
    "Add class" dialog on the class list. Same fields as the Add/Edit page (classes/_fields).
    It does not close on an outside click or Escape, so a half-filled form is never lost by accident.
    After a failed save the page comes back with the errors and this dialog reopens with what was typed.
--}}
@php
    $reopen = $errors->any() && old('_from_dialog');
@endphp
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="addClassLabel">Add class</h2>
                    <div class="small text-secondary">Teacher and room conflicts are checked when you save.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('classes.store') }}" novalidate>
                @csrf
                <input type="hidden" name="_from_dialog" value="1">
                <div class="modal-body dialog-form">
                    @if ($reopen)
                        <div class="alert alert-danger py-2 d-flex gap-2 align-items-start" role="alert">
                            <x-icon name="alert" :size="18" />
                            <div>The class was not saved. Please check the fields marked in red.</div>
                        </div>
                    @endif
                    @include('classes._fields', $form)
                </div>
                <div class="modal-footer">
                    <span class="small text-secondary me-auto d-none d-sm-inline">Fields marked <span class="text-danger fw-bold">*</span> are required.</span>
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Create class</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($reopen)
    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('addClassModal')).show();
            });
        </script>
    @endpush
@endif
