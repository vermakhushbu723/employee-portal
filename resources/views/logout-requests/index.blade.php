@extends('layouts.app')

@use('App\Support\Duration')

@section('title', 'Logout Requests')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Logout Requests</h1>
        <div class="small text-muted">
            Early logout / exception checkout requests stay <b>under review</b> until verified.
            @if ($isAdmin) Admin: <b>1-step</b> verification. @else Manager: <b>2-step</b> verification (verify, then confirm with your password). @endif
        </div>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger small py-2">{{ $errors->first() }}</div>
@endif

<nav class="seg-tabs mb-3">
    @foreach (['pending' => 'Under Review', 'approved' => 'Accepted', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
        <a href="{{ route($portal.'.logout-requests.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

<div data-live-region="logoutreq">
    @forelse ($items as $s)
        @php
            $u = $s->user;
            [$label, $tone] = match ($s->review_status) {
                'verified' => ['Step 1 verified — waiting for step 2', 'info'],
                'approved' => ['Accepted', 'success'],
                'rejected' => ['Rejected', 'danger'],
                default => ['Under Review', 'warning'],
            };
            $requested = $s->logout_requested_at ?? $s->check_out_at;
        @endphp
        <div class="card-soft card-pad mb-3 logout-req {{ $s->logoutPending() ? 'is-pending' : '' }}">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <span class="avatar tone-{{ $tone }}">{{ $u->initials }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <b>{{ $u->name }}</b> <span class="small text-muted">{{ $u->employee_code }} · {{ $u->employee?->department?->name ?? '—' }}</span>
                        <x-badge :tone="$tone">{{ $label }}</x-badge>
                    </div>
                    <div class="small mt-1">
                        <i class="bi bi-calendar3"></i> {{ $s->work_date->format('d M Y') }} ·
                        Punch-in <b>{{ $s->check_in_at->format('h:i A') }}</b> ·
                        Logout requested <b>{{ $requested?->format('h:i A') ?? '—' }}</b> ·
                        Worked <b>{{ Duration::hm((int) $s->active_seconds) }}</b> of {{ Duration::hm($u->currentShift()->required_minutes * 60) }}
                        @if ($s->work_score !== null) · Score <b>{{ $s->work_score }}%</b>@endif
                    </div>
                    <div class="wc-message mt-2 mb-0"><i class="bi bi-chat-square-text-fill"></i><div class="small"><b>Reason:</b> {{ $s->checkout_reason }}</div></div>
                    @if ($s->logout_verified_at)
                        <div class="small-xs text-muted mt-1"><i class="bi bi-check2-circle"></i> Step 1 by {{ $s->verifier?->name }} · {{ $s->logout_verified_at->format('d M, h:i A') }}@if ($s->logout_note) — “{{ $s->logout_note }}”@endif</div>
                    @endif
                    @if ($s->reviewed_at)
                        <div class="small-xs text-muted mt-1"><i class="bi bi-person-check"></i> {{ ucfirst($s->review_status) }} by {{ $s->reviewer?->name }} · {{ $s->reviewed_at->format('d M, h:i A') }}@if ($s->review_status === 'rejected' && $s->logout_note) — “{{ $s->logout_note }}”@endif</div>
                    @endif
                </div>

                @if ($s->logoutPending())
                    <div class="logout-actions">
                        @if ($isAdmin)
                            {{-- Admin: 1-step --}}
                            <form method="POST" action="{{ route($portal.'.logout-requests.approve', $s) }}" class="mb-2" data-confirm="Accept {{ $u->name }}'s logout request?">
                                @csrf
                                <input type="text" name="note" maxlength="500" class="form-control form-control-sm mb-1" placeholder="Note (optional)">
                                <button class="btn btn-success btn-sm w-100"><i class="bi bi-check-lg me-1"></i>Verify & Accept</button>
                            </form>
                        @elseif ($s->review_status === 'pending')
                            {{-- Manager step 1 --}}
                            <form method="POST" action="{{ route($portal.'.logout-requests.approve', $s) }}" class="mb-2">
                                @csrf
                                <div class="small fw-600 mb-1"><span class="step-pill">1</span> Verify</div>
                                <label class="form-check small"><input type="checkbox" name="checked_reason" value="1" class="form-check-input"> I checked the reason</label>
                                <label class="form-check small mb-1"><input type="checkbox" name="checked_work" value="1" class="form-check-input"> I checked today's work</label>
                                <input type="text" name="note" maxlength="500" class="form-control form-control-sm mb-1" placeholder="Verification note" required>
                                <button class="btn btn-primary btn-sm w-100">Step 1: Verify</button>
                            </form>
                        @else
                            {{-- Manager step 2 --}}
                            <form method="POST" action="{{ route($portal.'.logout-requests.approve', $s) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="step" value="2">
                                <div class="small fw-600 mb-1"><span class="step-pill">2</span> Confirm with your password</div>
                                <input type="password" name="password" class="form-control form-control-sm mb-1" placeholder="Your password" required autocomplete="current-password">
                                <button class="btn btn-success btn-sm w-100"><i class="bi bi-shield-check me-1"></i>Step 2: Confirm & Accept</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route($portal.'.logout-requests.reject', $s) }}">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="text" name="note" maxlength="500" class="form-control" placeholder="Reason to reject" required>
                                <button class="btn btn-outline-danger">Reject</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card-soft card-pad"><x-empty-state icon="box-arrow-right" title="No logout requests here" /></div>
    @endforelse
    @if ($items->hasPages())<div class="mt-2">{{ $items->links() }}</div>@endif
</div>
@endsection
