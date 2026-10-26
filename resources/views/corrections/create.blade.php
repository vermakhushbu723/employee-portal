@extends('layouts.app')

@use('App\Models\WorkCorrection')
@use('App\Support\Duration')

@section('title', 'Correct Work Data')
@section('back', route($portal.'.reports.daily', ['date' => $session->work_date->toDateString()]))

@section('content')
@php
    $u = $session->user;
    $locked = $session->isOpen() && $session->work_date->isToday();
    $oh = intdiv($original['active_seconds'], 3600);
    $om = intdiv($original['active_seconds'] % 3600, 60);
@endphp

<div class="card-soft card-pad mb-3 d-flex flex-wrap align-items-center gap-3">
    <span class="avatar lg tone-primary">{{ $u->initials }}</span>
    <div class="flex-grow-1 min-w-0">
        <div class="fw-bold">{{ $u->name }} <span class="text-muted fw-normal">({{ $u->employee_code }})</span></div>
        <div class="small text-muted">{{ $u->employee?->department?->name ?? 'No department' }} · Work date <b>{{ $session->work_date->format('l, d M Y') }}</b></div>
    </div>
    <div class="text-end small">
        Current score <b>{{ $original['score'] }}%</b>
        <div class="text-muted">{{ \App\Services\WorkScoreService::battery($original['grade'])[1] }}</div>
    </div>
</div>

@if ($locked)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>{{ $u->name }} is still punched in. Punch out first (Daily Work Report → Punch out), then correct the day.</div>
@elseif ($pending)
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-hourglass-split"></i>
        <div class="flex-grow-1">A correction for this day is already <b>waiting for review</b> by {{ $pending->reviewer?->name }}.</div>
        <a href="{{ route($portal.'.corrections.show', $pending) }}" class="btn btn-sm btn-primary">Open it</a>
    </div>
@else
    <form method="POST" action="{{ route($portal.'.corrections.store', $session) }}" class="row g-3">
        @csrf
        <div class="col-lg-7">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-1">1. Corrected data</h3>
                <div class="small text-muted mb-3">Change only what is wrong. The original value is shown on the left.</div>
                @error('corrected')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                @error('session')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Field</th><th class="text-muted">Original</th><th style="width:48%">Corrected</th></tr></thead>
                        <tbody>
                            @foreach (['calls' => 'telephone', 'interested' => 'people', 'leads_created' => 'file-earmark-plus'] as $f => $icon)
                                <tr>
                                    <td class="fw-600 text-nowrap"><i class="bi bi-{{ $icon }} me-1 text-muted"></i>{{ WorkCorrection::FIELDS[$f][0] }}</td>
                                    <td class="text-muted">{{ $original[$f] }}</td>
                                    <td><input type="number" name="{{ $f }}" min="0" max="5000" class="form-control @error($f) is-invalid @enderror" value="{{ old($f, $original[$f]) }}" required></td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="fw-600 text-nowrap"><i class="bi bi-clock me-1 text-muted"></i>Working Time</td>
                                <td class="text-muted">{{ Duration::hm($original['active_seconds']) }}</td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="work_hours" min="0" max="16" class="form-control" value="{{ old('work_hours', $oh) }}" required aria-label="Hours">
                                        <span class="input-group-text">h</span>
                                        <input type="number" name="work_minutes" min="0" max="59" class="form-control" value="{{ old('work_minutes', $om) }}" required aria-label="Minutes">
                                        <span class="input-group-text">m</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="info-box mt-3"><i class="bi bi-info-circle-fill"></i><div>After approval these numbers replace the original ones, and the day's <b>working time, work score and attendance are recalculated</b> (e.g. less working time can turn a Full Day into a Half Day).</div></div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-soft card-pad h-100">
                <h3 class="section-title mb-3">2. Correction remarks <span class="text-danger">*</span></h3>
                <label class="form-label required">Reason for the mistake</label>
                <input type="text" name="reason" maxlength="500" class="form-control mb-3 @error('reason') is-invalid @enderror" value="{{ old('reason') }}" required placeholder="e.g. Calls were marked twice / laptop was off during field visit">
                @error('reason')<div class="invalid-feedback d-block mt-n2 mb-2">{{ $message }}</div>@enderror

                <label class="form-label required">Correct information / details</label>
                <textarea name="details" rows="4" maxlength="2000" class="form-control mb-3 @error('details') is-invalid @enderror" required placeholder="What the correct numbers are and how you checked them">{{ old('details') }}</textarea>
                @error('details')<div class="invalid-feedback d-block mt-n2 mb-2">{{ $message }}</div>@enderror

                <h3 class="section-title mb-2 mt-1">3. Reviewer</h3>
                <select name="reviewer_id" class="form-select mb-1 @error('reviewer_id') is-invalid @enderror" required>
                    <option value="">Choose reviewer…</option>
                    @foreach ($reviewers as $r)
                        <option value="{{ $r->id }}" @selected(old('reviewer_id') == $r->id)>{{ $r->name }} — {{ $r->roleLabel() }}{{ $r->email ? '' : ' (no e-mail)' }}</option>
                    @endforeach
                </select>
                @error('reviewer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="small-xs text-muted mb-3">The reviewer gets an automatic e-mail with all details right after you submit.</div>

                <button class="btn btn-primary w-100"><i class="bi bi-send-check me-1"></i>Submit for Review</button>
            </div>
        </div>
    </form>
@endif

@if ($history->isNotEmpty())
    <div class="card-soft card-pad mt-3">
        <h3 class="section-title mb-2">Correction history for this day</h3>
        @foreach ($history as $h)
            @php [$hl, $ht] = $h->badge(); @endphp
            <a href="{{ route($portal.'.corrections.show', $h) }}" class="list-row list-row-link">
                <x-badge :tone="$ht">{{ $hl }}</x-badge>
                <div class="flex-grow-1 small">#{{ $h->id }} · {{ $h->reason }}</div>
                <span class="small-xs text-muted text-nowrap">{{ $h->submitter?->name }} · {{ $h->created_at->format('d M, h:i A') }}</span>
            </a>
        @endforeach
    </div>
@endif
@endsection
