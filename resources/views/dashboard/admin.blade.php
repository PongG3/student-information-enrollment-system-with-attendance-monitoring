{{--
    Administrator dashboard: only what the administrator acts on every day.
    Charts and trends live in Reports; this page keeps the header, what needs attention,
    four account and student cards, today's attendance and the latest activity.
--}}
<x-app-layout tab-title="Dashboard">
    @include('dashboard._header')

    @include('dashboard._attention')

    @include('dashboard._cards', ['compact' => true])

    <div class="row g-3">
        <div class="col-lg-7">
            @include('dashboard._today')
        </div>

        <div class="col-lg-5">
            <section class="card-sies h-100" aria-labelledby="recent-heading">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0" id="recent-heading">Recent activity</h2>
                    <a href="{{ route('audit-logs.index') }}" class="small fw-semibold text-decoration-none">View all →</a>
                </div>

                @if ($recent->isEmpty())
                    <x-empty-state message="No activity recorded yet." />
                @else
                    <ul class="activity-list">
                        @foreach ($recent as $log)
                            <li>
                                <div class="activity-text">{{ $log->description }}</div>
                                <div class="activity-meta">
                                    {{ $log->user?->name ?? 'System' }}@if ($log->role_label) ({{ $log->role_label }})@endif
                                    · <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y g:i A') }}">{{ $log->created_at->diffForHumans() }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
