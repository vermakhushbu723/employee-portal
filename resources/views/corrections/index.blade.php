@extends('layouts.app')

@use('App\Models\WorkCorrection')

@section('title', 'Correction Requests')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Work Data Correction & Review</h1>
        <div class="small text-muted">Wrong calls, interested leads, leads created or working time are corrected here — every correction is reviewed before it changes the employee's day.</div>
    </div>
    <a href="{{ route($portal.'.reports.daily') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New correction (from Daily Report)</a>
</div>

<nav class="seg-tabs mb-3">
    @foreach (['pending' => 'Pending Review', 'mine' => 'Waiting for Me', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All History'] as $key => $label)
        <a href="{{ route($portal.'.corrections.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

<div class="card-soft" data-live-region="status">
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead>
                <tr><th>#</th><th>Employee</th><th>Work Date</th><th>Changes</th><th>Reason</th><th>Submitted By</th><th>Reviewer</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($items as $c)
                    @php [$label, $tone] = $c->badge(); @endphp
                    <tr>
                        <td class="text-muted small">{{ $c->id }}</td>
                        <td>
                            <div class="fw-600 text-nowrap">{{ $c->user->name }}</div>
                            <div class="small-xs text-muted">{{ $c->user->employee_code }} · {{ $c->user->employee?->department?->name ?? '—' }}</div>
                        </td>
                        <td class="small text-nowrap">{{ $c->session?->work_date->format('d M Y') }}</td>
                        <td class="small">
                            @foreach (WorkCorrection::FIELDS as $f => [$fl])
                                @if ((int) $c->original[$f] !== (int) $c->corrected[$f])
                                    <div class="text-nowrap">{{ $fl }}: <s class="text-muted">{{ WorkCorrection::format($f, $c->original[$f]) }}</s> → <b>{{ WorkCorrection::format($f, $c->corrected[$f]) }}</b></div>
                                @endif
                            @endforeach
                        </td>
                        <td class="small" style="max-width:220px">{{ \Illuminate\Support\Str::limit($c->reason, 80) }}</td>
                        <td class="small text-nowrap">{{ $c->submitter?->name }}<div class="small-xs text-muted">{{ $c->created_at->format('d M, h:i A') }}</div></td>
                        <td class="small text-nowrap">{{ $c->reviewer?->name }}@if ($c->emailed_at)<div class="small-xs text-success"><i class="bi bi-envelope-check"></i> mailed</div>@endif</td>
                        <td><x-badge :tone="$tone">{{ $label }}</x-badge>@if ($c->reviewed_at)<div class="small-xs text-muted">{{ $c->reviewedBy?->name }} · {{ $c->reviewed_at->format('d M') }}</div>@endif</td>
                        <td class="text-end"><a href="{{ route($portal.'.corrections.show', $c) }}" class="btn btn-sm {{ $c->status === 'pending' && $c->reviewer_id === auth()->id() ? 'btn-primary' : 'btn-outline-primary' }}">{{ $c->status === 'pending' && $c->reviewer_id === auth()->id() ? 'Review' : 'View' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state icon="pencil-square" title="No corrections here" text="Open the Daily Work Report and click Correct on an employee's day." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->hasPages())<div class="card-pad pt-2">{{ $items->links() }}</div>@endif
</div>
@endsection
