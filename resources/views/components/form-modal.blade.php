@props(['id', 'addTitle', 'editTitle', 'storeUrl', 'updateUrl', 'size' => ''])
@php
    $editId = old('_edit_id');
    $action = $editId ? str_replace('__ID__', $editId, $updateUrl) : $storeUrl;
@endphp
{{-- Never taller than the window: the title and Save/Cancel stay in view, the fields scroll. --}}
<x-modal :id="$id" :title="$editId ? $editTitle : $addTitle" :size="trim($size . ' modal-dialog-scrollable')"
    data-form-modal data-store-action="{{ $storeUrl }}" data-add-title="{{ $addTitle }}" data-edit-title="{{ $editTitle }}">
    <form method="POST" action="{{ $action }}" novalidate>
        @csrf
        <input type="hidden" name="_method" value="{{ $editId ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_edit_id" value="{{ $editId }}">
        <div class="modal-body">
            {{ $slot }}
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-navy px-4">Save</button>
        </div>
    </form>
</x-modal>

@if ($errors->any())
    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById(@json($id))).show();
            });
        </script>
    @endpush
@endif
