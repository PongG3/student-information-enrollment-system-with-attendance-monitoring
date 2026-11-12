@php
    $good = $preview ? collect($preview['rows'])->where('errors', [])->count() : 0;
    $bad = $preview ? count($preview['rows']) - $good : 0;
@endphp
<x-app-layout title="Import Students" subtitle="Register many students at once from a CSV file. Nothing is saved until you confirm the preview."
    :back="route('students.index')" back-label="Back to students">

    @if (! $preview)
        <div class="row g-3">
            <div class="col-lg-5">
                <section class="card-sies p-4 h-100" aria-labelledby="upload-heading">
                    <h2 class="section-title mb-1" id="upload-heading">1. Upload the file</h2>
                    <p class="small text-secondary">Fill in the template in Excel or Google Sheets, then save it as <strong>CSV</strong>. Up to 500 students per file.</p>
                    <a href="{{ route('students.import.template') }}" class="btn btn-outline-secondary btn-sm-sies mb-3"><x-icon name="file" :size="14" /> Download the template</a>

                    <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <label class="form-label" for="importFile">CSV file <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="importFile" accept=".csv,text/csv" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button class="btn btn-navy mt-3">Check the file</button>
                    </form>
                </section>
            </div>
            <div class="col-lg-7">
                <section class="card-sies p-4 h-100" aria-labelledby="columns-heading">
                    <h2 class="section-title mb-1" id="columns-heading">Columns</h2>
                    <p class="small text-secondary mb-2">The first row must be these headings. <span class="text-danger fw-bold">*</span> = required. Student IDs are given automatically.</p>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach ($columns as $column => $required)
                            <code class="import-col">{{ $column }}@if ($required)<span class="text-danger">*</span>@endif</code>
                        @endforeach
                    </div>
                    <ul class="small text-secondary mb-0 ps-3">
                        <li><strong>birth_date</strong> as 2008-05-14 · <strong>gender</strong> Male, Female or Other</li>
                        <li><strong>program</strong> is the code: {{ $programs->implode(', ') }}</li>
                        <li><strong>student_type</strong> new, returning or transferee · <strong>year_level</strong> 1 to 6</li>
                        <li>The guardian becomes the emergency contact. More guardians can be added on each student's record.</li>
                        <li>A student already registered (same name and birth date) is skipped.</li>
                    </ul>
                </section>
            </div>
        </div>
    @else
        <section class="card-sies">
            <div class="card-header-sies">
                <div>
                    <h2 class="h6 fw-bold mb-1">2. Check the preview</h2>
                    <div class="small text-secondary">{{ $preview['file'] }} ·
                        <span class="text-success fw-semibold">{{ $good }} ready</span>
                        @if ($bad) · <span class="text-danger fw-semibold">{{ $bad }} with errors (will be skipped)</span>@endif
                    </div>
                </div>
            </div>
            <div class="table-responsive" data-fit-viewport>
                <table class="table table-sies align-middle mb-0">
                    <thead><tr><th>Line</th><th>Student</th><th>Program</th><th>Result</th></tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr @class(['table-danger-subtle' => $row['errors']])>
                                <td class="text-secondary">{{ $row['line'] }}</td>
                                <td class="fw-semibold">{{ $row['name'] ?: '—' }}</td>
                                <td>{{ $row['program'] ?: '—' }}</td>
                                <td>
                                    @if ($row['errors'])
                                        <ul class="mb-0 ps-3 small text-danger">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                                    @else
                                        <x-status-badge value="verified" label="Ready" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="form-actionbar is-sticky mt-3">
            <form method="POST" action="{{ route('students.import.store') }}" id="importConfirm" class="d-flex flex-wrap align-items-center gap-3 w-100" novalidate>
                @csrf
                <div class="form-check mb-0">
                    <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" value="1" id="importConsent">
                    <label class="form-check-label small" for="importConsent">
                        These students (or their guardians) agreed to the <a href="{{ route('privacy') }}" target="_blank">privacy notice</a>.
                    </label>
                    @error('consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-actions ms-sm-auto">
                    <button type="submit" form="importCancel" class="btn btn-outline-secondary px-4">Start over</button>
                    <button type="submit" class="btn btn-navy px-4" @disabled($good === 0)>Register {{ $good }} {{ \Illuminate\Support\Str::plural('student', $good) }}</button>
                </div>
            </form>
            <form method="POST" action="{{ route('students.import.cancel') }}" id="importCancel" class="d-none">@csrf</form>
        </div>
    @endif
</x-app-layout>
