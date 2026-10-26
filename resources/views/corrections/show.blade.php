@extends('layouts.app')

@use('App\Models\WorkCorrection')

@section('title', 'Correction #'.$c->id)
@section('back', route($portal.'.corrections.index'))

@section('content')
@php
    [$label, $tone] = $c->badge();
    $u = $c->user;
@endphp

<div class="card-soft card-pad mb-3 d-flex flex-wrap align-items-center gap-3">
    <span class="avatar lg tone-primary">{{ $u->initials }}</span>
    <div class="flex-grow-1 min-w-0">
        <div class="fw-bold">{{ $u->name }} <span class="text-muted fw-normal">({{ $u->employee_code }})</span></div>
        <div class="small text-muted">{{ $u->employee?->department?->name ?? 'No department' }} · Work date <b>{{ $c->session->work_date->format('l, d M Y') }}</b> · Request #{{ $c->id }}</div>
    </div>
    <x-badge :tone="$tone" class="fs-6">{{ $label }}</x-badge>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft card-pad mb-3">
            <h3 class="section-title mb-3">Original vs Corrected Data</h3>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Field</th><th>Original Data</th><th>Corrected Data</th></tr></thead>
                    <tbody>
                        @foreach (WorkCorrection::FIELDS as $f => [$fl])
                            @php $changed = (int) $c->original[$f] !== (int) $c->corrected[$f]; @endphp
                            <tr @class(['table-warning' => $changed])>
                                <td class="fw-600">{{ $fl }}</td>
                                <td class="text-muted">{{ WorkCorrection::format($f, $c->original[$f]) }}</td>
                                <td class="{{ $changed ? 'fw-bold text-danger' : '' }}">{{ WorkCorrection::format($f, $c->corrected[$f]) }}@if ($changed) <i class="bi bi-pencil-fill small"></i>@endif</td>
                            </tr>
                        @endforeach
                        <tr><td class="fw-600">Work score at submit</td><td class="text-muted">{{ $c->original['score'] }}% · {{ \App\Services\WorkScoreService::battery($c->original['grade'])[1] }}</td><td>
                            @if ($c->status === 'approved')
                                <b>{{ $c->session->work_score }}%</b> · {{ \App\Services\WorkScoreService::battery($c->session->grade ?? 'none')[1] }}
                            @else
                                <span class="text-muted small">recalculated on approval</span>
                            @endif
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-soft card-pad mb-3">
            <div class="wc-message mb-2"><i class="bi bi-exclamation-circle-fill"></i><div><div class="fw-600 small">Correction Reason</div><div class="small">{{ $c->reason }}</div></div></div>
            <div class="info-box"><i class="bi bi-card-text"></i><div><div class="fw-600">Correct information / details</div>{!! nl2br(e($c->details)) !!}</div></div>
        </div>
    </div>

    <div class="col-lg-5">
        @if ($canReview)
            <div class="card-soft card-pad mb-3">
                <h3 class="section-title mb-2">Review</h3>
                @error('review')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                <form method="POST" action="{{ route($portal.'.corrections.approve', $c) }}" class="mb-3" data-confirm="Approve? The corrected numbers will replace the original and the day's score and attendance will be recalculated.">
                    @csrf
                    <label class="form-label small">Note (optional)</label>
                    <input type="text" name="note" maxlength="500" class="form-control mb-2" placeholder="e.g. Verified with call logs">
                    <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Approve Correction</button>
                </form>
                <form method="POST" action="{{ route($portal.'.corrections.reject', $c) }}">
                    @csrf
                    <label class="form-label small required">Reason for rejecting</label>
                    <input type="text" name="note" maxlength="500" class="form-control mb-2 @error('note') is-invalid @enderror" required>
                    @error('note')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                </form>
            </div>
        @elseif ($c->status === 'pending')
            <div class="alert alert-info small"><i class="bi bi-hourglass-split me-1"></i>Waiting for review by <b>{{ $c->reviewer?->name }}</b>.</div>
        @endif

        <div class="card-soft card-pad">
            <h3 class="section-title mb-3">Audit History</h3>
            <ul class="audit-trail list-unstyled mb-0 small">
                <li><b>Submitted</b> by {{ $c->submitter?->name }} ({{ $c->submitter?->roleLabel() }})<div class="text-muted">{{ $c->created_at->format('d M Y, h:i:s A') }}</div></li>
                <li><b>Reviewer</b> {{ $c->reviewer?->name }}
                    <div class="text-muted">{{ $c->emailed_at ? 'Review e-mail sent '.$c->emailed_at->format('d M, h:i A') : 'Review e-mail not sent (no mail or address)' }}</div></li>
                @if ($c->reviewed_at)
                    <li><b class="text-{{ $tone }}">{{ $label }}</b> by {{ $c->reviewedBy?->name }}<div class="text-muted">{{ $c->reviewed_at->format('d M Y, h:i:s A') }}</div>@if ($c->review_note)<div>“{{ $c->review_note }}”</div>@endif</li>
                @endif
            </ul>
            @if ($history->isNotEmpty())
                <hr>
                <div class="small fw-600 mb-1">Other corrections of this day</div>
                @foreach ($history as $h)
                    @php [$hl, $ht] = $h->badge(); @endphp
                    <a href="{{ route($portal.'.corrections.show', $h) }}" class="d-flex gap-2 small mb-1"><x-badge :tone="$ht">{{ $hl }}</x-badge> #{{ $h->id }} · {{ $h->created_at->format('d M h:i A') }}</a>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
