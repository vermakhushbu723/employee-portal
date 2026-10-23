@extends('layouts.app')

@use('App\Support\Duration')
@use('App\Services\WorkScoreService')

@section('title', 'Daily Work Report')

@section('content')
<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2 align-items-center">
    <label class="small fw-600 me-1" for="reportDate">Date</label>
    <input type="date" id="reportDate" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control w-auto" onchange="this.form.submit()">
    <span class="small text-muted">{{ $date->format('l, d M Y') }}</span>
    <span class="ms-auto"></span>
    @if ($isAdmin)
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Download</a>
    @else
        <span class="small text-muted"><i class="bi bi-eye"></i> View only — reports can be downloaded by admin</span>
    @endif
</form>

@if ($isAdmin && ! $mailReady)
    <div class="alert alert-warning small"><i class="bi bi-envelope-exclamation me-1"></i>Daily report e-mails are not set up yet (no mail account configured). Reports are saved here; e-mails start once SMTP is added.</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-tile icon="battery-full" label="Full Day" tone="success" :value="$summary['full']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="battery-half" label="Half Day" tone="warning" :value="$summary['half']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="battery" label="Quarter Day" tone="purple" :value="$summary['quarter']" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="x-lg" label="Absent / 0%" tone="secondary" :value="$summary['absent']" /></div>
</div>

<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-soft">
            <thead>
                <tr>
                    <th>Employee</th><th>In / Out</th><th>Active</th><th>Idle</th><th>Break</th>
                    <th>Leads</th><th>Interested</th><th>Tasks</th><th style="min-width:150px">Work Score</th>
                    @if ($isAdmin)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $s = $row['session'];
                        $w = $row['work'];
                        [$lvl, $gradeLabel, $tone] = WorkScoreService::battery($w['grade'] ?? 'none');
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-600">{{ $row['user']->name }}</div>
                            <div class="small-xs text-muted">{{ $row['user']->employee_code }} · {{ $row['user']->employee?->manager?->name ?? 'No manager' }}</div>
                        </td>
                        @if ($s)
                            <td class="small text-nowrap">{{ $s->check_in_at->format('h:i A') }}@if ($s->is_late) <x-badge tone="danger">Late</x-badge>@endif
                                <br><span class="text-muted">{{ $s->check_out_at?->format('h:i A') ?? 'Working…' }}</span>
                                @if ($s->checkout_type === 'auto')<x-badge tone="secondary" title="Browser closed / device off — closed at last activity">auto</x-badge>@endif
                            </td>
                            <td class="text-success fw-600 text-nowrap">{{ Duration::hm($w['active']) }}</td>
                            <td class="text-danger text-nowrap">{{ Duration::hm($w['idle']) }}</td>
                            <td class="text-nowrap">{{ Duration::hm($w['break']) }}</td>
                            <td class="text-nowrap">{{ $w['created'] }} / {{ $w['targets']['leads'] }}</td>
                            <td class="text-nowrap">{{ $w['interested'] }} / {{ $w['targets']['interested'] }}</td>
                            <td class="text-nowrap">{{ $row['tasks_done'] }} / {{ $row['tasks_total'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="battery tone-{{ $tone }}"><span style="width: {{ max(4, min(100, $w['score'])) }}%"></span></span>
                                    <span class="fw-bold">{{ $w['score'] }}%</span>
                                    <x-badge :tone="$tone">{{ $gradeLabel }}</x-badge>
                                </div>
                                @if ($s->correction_note)<div class="small-xs text-muted mt-1"><i class="bi bi-pencil"></i> {{ $s->correction_note }}</div>@endif
                                @if ($s->report_sent_at)<div class="small-xs text-success"><i class="bi bi-envelope-check"></i> mailed {{ $s->report_sent_at->format('h:i A') }}</div>@endif
                            </td>
                            @if ($isAdmin)
                                <td class="text-end text-nowrap">
                                    @if ($s->isOpen())
                                        <form method="POST" action="{{ route('admin.reports.punch-out', $s) }}" class="d-inline" data-confirm="Punch out {{ $row['user']->name }} now?">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Punch out</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-modal="#correctModal"
                                                data-action="{{ route('admin.reports.correct', $s) }}"
                                                data-fill='@json(['employee' => $row['user']->name.' — '.$date->format('d M Y'), 'grade' => $w['grade'], 'note' => $s->correction_note])'>Correct</button>
                                        <form method="POST" action="{{ route('admin.reports.send', $s) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-light" title="E-mail this report"><i class="bi bi-envelope"></i></button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        @else
                            <td colspan="8" class="text-muted small">{{ $date->isToday() ? 'Not punched in yet' : 'No attendance (absent / leave / weekly off)' }}</td>
                            @if ($isAdmin)<td></td>@endif
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty-state icon="people" title="No employees to show" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($isAdmin)
    <div class="modal fade" id="correctModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" class="modal-content rounded-4 border-0">
                @csrf
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Correct Attendance</h5>
                        <div class="small text-muted" data-field="employee"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <label class="form-label required">Attendance for the day</label>
                    <select name="grade" class="form-select mb-3">
                        <option value="full">Full Day</option>
                        <option value="half">Half Day</option>
                        <option value="quarter">Quarter Day</option>
                        <option value="none">Absent</option>
                    </select>
                    <label class="form-label required">Reason / note</label>
                    <textarea name="note" rows="2" maxlength="500" class="form-control" required placeholder="e.g. Field visit approved by manager"></textarea>
                    <div class="small text-muted mt-2">The updated report is e-mailed to the employee and their manager.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save Correction</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
