{{--
    "Add academic year" dialog on the Academic Years & Terms page. Same fields and rules as the full page.
    Does not close on an outside click or Escape; after a failed save it reopens with what was typed.
--}}
@php
    $reopen = $errors->any() && old('_from_dialog');
@endphp
<div class="modal fade" id="addYearModal" tabindex="-1" aria-labelledby="addYearLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="addYearLabel">Add academic year</h2>
                    <div class="small text-secondary">Dates may not overlap another academic year.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('academic-years.store') }}" novalidate>
                @csrf
                <input type="hidden" name="_from_dialog" value="1">
                <div class="modal-body">
                    @if ($reopen)
                        <div class="alert alert-danger py-2 d-flex gap-2 align-items-start" role="alert">
                            <x-icon name="alert" :size="18" />
                            <div>The academic year was not saved. Please check the fields marked in red.</div>
                        </div>
                    @endif
                    <x-field name="name" id="yearName" label="Name" required placeholder="e.g. 2026-2027" help="Format: 2026-2027" />
                    <div class="row">
                        <div class="col-sm-6"><x-field name="start_date" id="yearStart" type="date" label="Start date" required /></div>
                        <div class="col-sm-6"><x-field name="end_date" id="yearEnd" type="date" label="End date" required /></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy px-4">Add academic year</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($reopen)
    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('addYearModal')).show();
            });
        </script>
    @endpush
@endif
