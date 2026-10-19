@extends('layouts.app')

@use('App\Enums\LeaveType')
@use('App\Support\Duration')

@section('title', 'Leave Summary')
@section('back', route('employee.leaves.index'))

@section('content')
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><x-stat-tile class="plain" icon="calendar3" label="Total Leave" tone="primary" :value="($totals['total'] + 0).' Days'" /></div>
    <div class="col-6 col-md-3"><x-stat-tile class="plain" icon="calendar-x" label="Used Leave" tone="danger" :value="($totals['used'] + 0).' Days'" /></div>
    <div class="col-6 col-md-3"><x-stat-tile class="plain" icon="calendar-check" label="Balance Leave" tone="success" :value="($totals['available'] + 0).' Days'" /></div>
    <div class="col-6 col-md-3"><x-stat-tile class="plain" icon="trophy" label="Leave Charges" tone="danger" :value="'₹ '.number_format($totals['charges'])" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-soft card-pad mb-3">
            <div class="section-head">
                <h3 class="section-title">Leave Balance</h3>
                <span class="small text-muted">{{ $year }}</span>
            </div>
            @foreach (LeaveType::balanced() as $type)
                @php
                    $b = $balances->get($type->value);
                @endphp
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="small" style="width:110px">{{ $type->label() }}</span>
                    <x-progress class="flex-grow-1" :value="$b ? Duration::percent($b->used, $b->total) : 0" tone="primary" />
                    <span class="small fw-600 text-nowrap">{{ $b ? ($b->used + 0).' / '.($b->total + 0) : '0 / 0' }}</span>
                </div>
            @endforeach
            <div class="d-flex align-items-center gap-3 pt-2 border-top">
                <span class="small fw-600" style="width:110px">Total Available</span>
                <x-progress class="flex-grow-1" :value="Duration::percent($totals['available'], $totals['total'])" tone="primary" />
                <span class="small fw-600 text-nowrap">{{ $totals['available'] + 0 }} / {{ $totals['total'] + 0 }}</span>
            </div>
        </div>

        <div class="card-soft card-pad">
            <h3 class="section-title mb-2">Quick Info</h3>
            <div class="info-box">
                <i class="bi bi-info-circle-fill"></i>
                <div>Approved leaves are paid as per company policy. Rejected leave or extra leave will be deducted from your salary. Unpaid days this year: <b>{{ $unpaidDays + 0 }}</b>.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-soft card-pad">
            <div class="section-head">
                <h3 class="section-title">Leave Charges / Deductions</h3>
                <form method="GET">
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach (range(now()->year, now()->year - 3) as $y)
                            <option value="{{ $y }}" @selected($y === $year)>{{ $y === now()->year ? 'This Year' : $y }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @forelse ($deductions as $deduction)
                <div class="list-row">
                    <span class="fw-600 small" style="width:72px">{{ $deduction->record_date->format('M Y') }}</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600 small">{{ $deduction->title }}</div>
                        <div class="small-xs text-muted">{{ $deduction->description }}</div>
                    </div>
                    <span class="fw-bold text-danger text-nowrap">- ₹{{ number_format($deduction->amount) }}</span>
                </div>
            @empty
                <x-empty-state icon="emoji-smile" title="No deductions" text="No leave charges for {{ $year }}." />
            @endforelse

            <div class="status-banner tone-danger mt-3">
                <span class="dot"><i class="bi bi-coin"></i></span>
                <div>
                    Total Leave Charges (Deducted)
                    <small class="fs-5 fw-bold text-danger">₹ {{ number_format($totals['charges']) }}</small>
                </div>
            </div>
            <p class="small-xs text-muted mt-2 mb-0">Actual deductions come from the applicable HR policy and approved payroll records.</p>
        </div>
    </div>
</div>
@endsection
