{{-- Today's attendance: counts, sheets recorded, and each class with its action. Needs: $today, $isTeacher. --}}
@php $role = auth()->user()->role; @endphp
<div class="card-sies h-100">
    <div class="card-header-sies">
        <h2 class="h6 fw-bold mb-0">{{ $isTeacher ? "Today's classes" : "Today's attendance" }}</h2>
        <span class="small text-secondary">{{ today()->format('D, M j') }}</span>
    </div>
    <div class="p-3">
        <div class="row g-2 text-center mb-2">
            @foreach ([['Present', $today['counts']['present'], 'success'], ['Late', $today['counts']['late'], 'warning'], ['Absent', $today['counts']['absent'], 'danger'], ['Excused', $today['counts']['excused'], 'info']] as [$label, $value, $variant])
                <div class="col-3">
                    <div class="rounded-3 py-1 bg-{{ $variant }}-subtle">
                        <div class="fs-5 fw-bold lh-sm">{{ $value }}</div>
                        <div class="small text-secondary">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($today['note'])
            <div class="alert alert-secondary py-2 small mb-3">{{ $today['note'] }}</div>
        @endif

        @if ($today['expected'] > 0)
            @php
                $progress = (int) round($today['recorded'] / $today['expected'] * 100);
            @endphp
            <div class="d-flex justify-content-between small mb-1">
                <span>Sheets recorded</span><span class="fw-semibold">{{ $today['recorded'] }} of {{ $today['expected'] }}</span>
            </div>
            <div class="progress mb-3" role="progressbar" aria-label="Sheets recorded today" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                <div class="progress-bar" style="width: {{ $progress }}%"></div>
            </div>

            @php $canTake = in_array($role, ['admin', 'teacher'], true); @endphp
            <ul class="list-group list-group-flush">
                {{-- Classes still waiting for a sheet come first: that is what needs doing. --}}
                @foreach ($today['classes']->sortBy(fn ($row) => $row['recorded'] ? 1 : 0)->values() as $row)
                    @php $sheetUrl = route('attendance.sheet', ['class_offering' => $row['class']->id, 'date' => today()->toDateString()]); @endphp
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <span class="fw-semibold">{{ $row['class']->class_code }}</span>
                            <span class="small text-secondary">· {{ $row['class']->subject->code }} · {{ $row['class']->scheduleSummary() }}</span>
                        </div>
                        <div class="text-nowrap">
                            @if ($row['recorded'])
                                <x-status-badge value="verified" label="Recorded" />
                                <a href="{{ $sheetUrl }}" class="btn btn-outline-secondary btn-sm-sies ms-1">Open</a>
                            @else
                                @unless ($isTeacher)
                                    <x-status-badge value="pending" label="Not yet" />
                                @endunless
                                @if ($canTake)
                                    <a href="{{ $sheetUrl }}" class="btn btn-navy btn-sm-sies ms-1">Take attendance</a>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
