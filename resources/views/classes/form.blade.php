@php($editing = $class->exists)
<x-app-layout :title="$editing ? 'Edit Class' : 'Add Class'"
    subtitle="Teacher and room conflicts are checked when you save."
    :back="$editing ? route('classes.show', $class) : route('classes.index')" :back-label="$editing ? 'Back to class' : 'Back to classes'">
    <form method="POST" action="{{ $editing ? route('classes.update', $class) : route('classes.store') }}" novalidate @class(['dialog-form' => request()->boolean('embed')])>
        @csrf
        @if ($editing) @method('PUT') @endif

        @include('classes._fields')

        <x-form-actions sticky class="mt-3" :submit="$editing ? 'Save changes' : 'Create class'" :cancel="$editing ? route('classes.show', $class) : route('classes.index')" />
    </form>
</x-app-layout>
