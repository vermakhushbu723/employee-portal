{{--
    Full-screen "Punch in to start your day" popup for employees who have not
    punched in yet. Cannot be closed. It refreshes by itself (live region
    "punch"), e.g. when admin approves a late check-in request.
--}}
<div data-live-region="punch">
    @if ($punchGate)
        <div class="punch-gate" role="dialog" aria-modal="true" aria-labelledby="punchGateTitle">
            <div class="punch-card">
                <span class="brand-mark mx-auto mb-2" style="width:56px;height:56px"><img src="{{ asset('images/logo-mark.png') }}" alt=""></span>
                <div class="small text-muted">{{ now()->hour < 12 ? 'Good Morning' : (now()->hour < 17 ? 'Good Afternoon' : 'Good Evening') }}, {{ auth()->user()->name }} 👋</div>
                <h2 class="h5 fw-bold mb-1" id="punchGateTitle">Punch in to start your day</h2>
                <div class="small text-muted mb-3">
                    {{ now()->format('l, d M Y') }} · Shift {{ auth()->user()->currentShift()->label() }}<br>
                    On-time punch-in: <b>{{ $punchGate['opens_at'] }} – {{ $punchGate['late_after'] }}</b>
                </div>

                <div class="punch-live-clock mb-3" data-clock>{{ now()->format('h:i:s A') }}</div>

                @if ($errors->has('action'))
                    <div class="alert alert-danger small text-start">{{ $errors->first('action') }}</div>
                @endif

                @include('employee.attendance.partials.checkin-action', ['checkIn' => $punchGate])

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-link btn-sm text-muted"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button>
                </form>
            </div>
        </div>
    @endif
</div>
