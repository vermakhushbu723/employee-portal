{{-- Employee: logout request under review banner, then the decision popup. $logoutSession from the layouts.app composer. --}}
<div data-live-region="logoutme">
    @if ($logoutSession)
        @php $s = $logoutSession; @endphp
        @if ($s->logoutPending())
            <div class="logout-review-banner">
                <span class="spinner-grow spinner-grow-sm"></span>
                <div class="flex-grow-1">
                    <b>Logout request under review</b>
                    <div class="small">Sent at {{ ($s->logout_requested_at ?? $s->check_out_at)?->format('h:i A') }}{{ $s->review_status === 'verified' ? ' · step 1 verified by your manager, waiting for final confirmation' : ' · waiting for your manager / admin' }}. You can sign out once it is accepted.</div>
                </div>
            </div>
        @else
            @php $ok = $s->review_status === 'approved'; @endphp
            <div class="modal fade" id="logoutDecision" tabindex="-1" data-autoshow data-seen-url="{{ route('employee.attendance.decision-seen') }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content rounded-4 border-0 text-center p-3">
                        <div class="modal-body">
                            <span class="decision-icon {{ $ok ? 'ok' : 'no' }}"><i class="bi bi-{{ $ok ? 'check-lg' : 'x-lg' }}"></i></span>
                            <h5 class="fw-bold mt-3">{{ $ok ? 'Your Logout Request Accepted By Management' : 'Your Logout Request Was Not Accepted' }}</h5>
                            <p class="text-muted small mb-1">{{ $ok ? 'Accepted' : 'Reviewed' }} by {{ $s->reviewer?->publicName() ?? 'management' }} at {{ $s->reviewed_at?->format('h:i A') }}.</p>
                            @if ($s->logout_note)<p class="small mb-1">“{{ $s->logout_note }}”</p>@endif
                            <p class="small mb-0">{{ $ok ? 'Your day is closed. You can sign out now.' : 'Your day is open again — please continue your work.' }}</p>
                        </div>
                        <div class="d-flex gap-2 justify-content-center pb-2">
                            @if ($ok)
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button></form>
                            @endif
                            <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
