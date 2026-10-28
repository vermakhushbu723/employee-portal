@extends('layouts.app')

@use('App\Support\Duration')
@use('App\Enums\AttendanceStatus')

@section('title', 'My Attendance')
@section('back', route('employee.dashboard'))

@php
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth();
    $canNext = $next->lte(now()->startOfMonth());
@endphp

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft card-pad mb-3 d-flex align-items-center justify-content-between">
            <a href="{{ route('employee.attendance.history', ['month' => $prev]) }}" class="btn-icon" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <span class="fw-bold fs-6">{{ $month->format('F Y') }}</span>
            @if ($canNext)
                <a href="{{ route('employee.attendance.history', ['month' => $next->format('Y-m')]) }}" class="btn-icon" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
            @else
                <span class="btn-icon opacity-25"><i class="bi bi-chevron-right"></i></span>
            @endif
        </div>

        <div class="row g-2 mb-3">
            <div class="col"><x-stat-tile class="flex-column text-center" icon="battery-full" label="Full Day" tone="success" :value="$counts['present']" /></div>
            <div class="col"><x-stat-tile class="flex-column text-center" icon="battery-half" label="Half Day" tone="warning" :value="$counts['half_day']" /></div>
            <div class="col"><x-stat-tile class="flex-column text-center" icon="battery" label="Quarter" tone="purple" :value="$counts['quarter_day']" /></div>
            <div class="col"><x-stat-tile class="flex-column text-center" icon="x-lg" label="Absent" tone="secondary" :value="$counts['absent']" /></div>
            <div class="col"><x-stat-tile class="flex-column text-center" icon="calendar-x" label="Leave" tone="danger" :value="$counts['leave']" /></div>
        </div>

        <div class="card-soft card-pad mb-3">
            <div class="cal-grid mb-2">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                    <div class="dow">{{ $dow }}</div>
                @endforeach

                @for ($i = 0; $i < $leading_blanks; $i++)
                    <div></div>
                @endfor

                @foreach ($days as $day)
                    @php
                        $status = $day['status'];
                        $session = $day['session'];
                        [$level, $gradeLabel, $gradeTone] = \App\Services\WorkScoreService::battery($day['grade']);
                        $detail = [
                            'date' => $day['date']->format('l, d M Y'),
                            'status' => ($gradeLabel ?: $status?->label() ?? '—').($day['score'] !== null ? " · {$day['score']}% work" : ''),
                            'login' => $session?->check_in_at->format('h:i A') ?? '—',
                            'logout' => $session?->check_out_at?->format('h:i A') ?? ($session ? 'Not punched out' : '—'),
                            'break' => $session ? $session->break_minutes.' min' : '—',
                            'worked' => $session ? Duration::hm((int) $session->active_seconds).' active' : '—',
                            'leads' => $session ? "{$session->leads_created} created · {$session->interested_count} interested" : '—',
                            'note' => $day['leave'] ? $day['leave']->leave_type->label().' — '.$day['leave']->reason : ($session?->correction_note ? 'Our Management Team note: '.$session->correction_note : ''),
                        ];
                        $clickable = $status !== null && $status !== AttendanceStatus::WeeklyOff;
                        $tone = $level !== null ? $gradeTone : $status?->tone();
                        $classes = ['cal-cell'];
                        if ($clickable) { $classes[] = 'has-status tone-'.$tone; }
                        if ($day['date']->isToday()) { $classes[] = 'today'; }
                        elseif ($day['date']->isFuture()) { $classes[] = 'future'; }
                    @endphp
                    <div>
                        <button type="button" class="{{ implode(' ', $classes) }}"
                                @if ($clickable) data-modal="#dayModal" data-fill='@json($detail)' @endif
                                title="{{ $gradeLabel ?: $status?->label() }}{{ $day['score'] !== null ? ' — '.$day['score'].'%' : '' }}">
                            <span class="d">{{ $day['date']->day }}</span>
                            @if ($level !== null)
                                <span class="battery tone-{{ $gradeTone }}"><span style="width: {{ max($level, 4) }}%"></span></span>
                            @elseif ($status === AttendanceStatus::Leave)
                                <span class="small-xs">Leave</span>
                            @endif
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="legend mt-3">
                <span class="tone-success">Full Day (100% work)</span>
                <span class="tone-warning">Half Day (50%+)</span>
                <span class="tone-purple">Quarter (25%+)</span>
                <span class="tone-secondary">Absent</span>
                <span class="tone-danger">Leave</span>
            </div>
            <div class="small-xs text-muted mt-1">Work % = average of active time, leads created and interested/mature leads against your daily targets.</div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-soft card-pad mb-3">
            <div class="row text-center g-0">
                <div class="col-6 border-end">
                    <div class="text-muted small">Total Working Hours</div>
                    <div class="fw-bold fs-5">{{ Duration::minutes($worked_minutes) }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Weekly Off</div>
                    <div class="fw-bold fs-5">{{ $counts['weekly_off'] }} days</div>
                </div>
            </div>
        </div>

        <div class="card-soft card-pad">
            <h3 class="section-title mb-2">Attendance Details</h3>
            @forelse ($recent as $day)
                @php
                    $status = $day['status'];
                @endphp
                <div class="list-row">
                    <span class="date-chip tone-{{ $status->tone() }}"><b>{{ $day['date']->format('d') }}</b><small>{{ $day['date']->format('M') }}</small></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600" style="color: var(--{{ $status->tone() === 'secondary' ? 'muted' : $status->tone() }})">{{ $status->label() }}</div>
                        @if ($day['session'])
                            <div class="small text-muted">
                                Login: {{ $day['session']->check_in_at->format('h:i A') }}
                                · Logout: {{ $day['session']->check_out_at?->format('h:i A') ?? '—' }}
                            </div>
                            <div class="small text-muted">Work Hours: {{ Duration::minutes($day['session']->worked_minutes) }}</div>
                        @elseif ($day['leave'])
                            <div class="small text-muted">{{ $day['leave']->leave_type->label() }}</div>
                        @else
                            <div class="small text-muted">--</div>
                        @endif
                    </div>
                    @if ($day['leave'])
                        <x-badge tone="success">Approved</x-badge>
                    @endif
                </div>
            @empty
                <x-empty-state icon="calendar" title="No attendance records for this month" />
            @endforelse
        </div>
    </div>
</div>

<div class="modal fade" id="dayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0" data-field="date"></h5>
                    <div class="small text-muted" data-field="status"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="kv-row"><span class="k">Login Time</span><span class="v" data-field="login"></span></div>
                <div class="kv-row"><span class="k">Logout Time</span><span class="v" data-field="logout"></span></div>
                <div class="kv-row"><span class="k">Break Duration</span><span class="v" data-field="break"></span></div>
                <div class="kv-row"><span class="k">Working Time</span><span class="v" data-field="worked"></span></div>
                <div class="kv-row"><span class="k">Leads</span><span class="v" data-field="leads"></span></div>
                <div class="small text-muted mt-2" data-field="note"></div>
            </div>
        </div>
    </div>
</div>
@endsection
