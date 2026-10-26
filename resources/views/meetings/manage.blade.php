@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h5 fw-bold mb-0">Meetings</h1>
        <div class="small text-muted">Customer meetings of {{ $portal === 'admin' ? 'all employees' : 'your team' }}. A meeting you give stays <b>Pending</b> until the employee accepts it.</div>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-plus-lg me-1"></i>Give Meeting</button>
</div>

<nav class="seg-tabs mb-3">
    @foreach (['today' => 'Today', 'upcoming' => 'Upcoming', 'pending' => 'Pending Acceptance', 'past' => 'Past'] as $key => $label)
        <a href="{{ route($portal.'.meetings.index', ['tab' => $key]) }}" @class(['active' => $tab === $key])>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

<div class="card-soft" data-live-region="status">
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Date & Time</th><th>Customer</th><th>Title</th><th>Employee</th><th>Platform</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($meetings as $m)
                    @php [$label, $tone] = $m->badge(); @endphp
                    <tr>
                        <td class="text-nowrap"><b>{{ $m->scheduled_at->format('d M Y') }}</b><div class="small text-muted">{{ $m->scheduled_at->format('h:i A') }}</div></td>
                        <td>{{ $m->customer_name }}<div class="small-xs text-muted">{{ $m->customer_mobile }} {{ $m->city ? '· '.$m->city : '' }}</div></td>
                        <td class="small">{{ $m->title }}</td>
                        <td class="small text-nowrap">{{ $m->user->name }}<div class="small-xs text-muted">{{ $m->user->employee_code }}</div></td>
                        <td class="small text-nowrap"><i class="bi bi-{{ $m->platformIcon() }}"></i> {{ $m->platformLabel() }}</td>
                        <td><x-badge :tone="$tone">{{ $label }}</x-badge>@if ($m->status === 'live' && $m->started_at)<div class="small-xs text-muted">since {{ $m->started_at->format('h:i A') }}</div>@endif</td>
                        <td class="text-end text-nowrap">
                            @if (in_array($m->status, ['upcoming', 'live'], true) && ($m->isPortal() || $m->link))
                                @if ($m->isPortal())
                                    <a href="{{ route($portal.'.meetings.room', $m) }}" class="btn btn-sm btn-primary"><i class="bi bi-camera-video"></i> Join</a>
                                    <button type="button" class="btn btn-sm btn-light" data-copy="{{ $m->joinLink() }}" title="Copy customer join link"><i class="bi bi-link-45deg"></i></button>
                                @else
                                    <a href="{{ $m->link }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Join</a>
                                @endif
                            @endif
                            @if (in_array($m->status, ['pending', 'upcoming'], true))
                                <form method="POST" action="{{ route($portal.'.meetings.cancel', $m) }}" class="d-inline" data-confirm="Cancel this meeting?">@csrf<button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="calendar-event" title="No meetings here" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($meetings->hasPages())<div class="card-pad pt-2">{{ $meetings->links() }}</div>@endif
</div>

<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route($portal.'.meetings.store') }}" class="modal-content rounded-4 border-0">
            @csrf
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Give a Meeting to an Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0 row g-3">
                <div class="col-12">
                    <label class="form-label required">Employee</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Choose employee…</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('user_id') == $s->id)>{{ $s->name }} ({{ $s->employee_code }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label required">Customer name</label><input name="customer_name" class="form-control" value="{{ old('customer_name') }}" required maxlength="120"></div>
                <div class="col-md-4"><label class="form-label">Mobile</label><input name="customer_mobile" class="form-control" value="{{ old('customer_mobile') }}" maxlength="20"></div>
                <div class="col-md-3"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city') }}" maxlength="80"></div>
                <div class="col-12"><label class="form-label required">Meeting title</label><input name="title" class="form-control" value="{{ old('title') }}" required maxlength="150"></div>
                <div class="col-md-4">
                    <label class="form-label required">Platform</label>
                    <select name="platform" class="form-select">
                        @foreach ($platforms as $key => [$plabel])<option value="{{ $key }}" @selected(old('platform') === $key)>{{ $plabel }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label required">Date</label><input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required></div>
                <div class="col-md-4"><label class="form-label required">Time</label><input type="time" name="time" class="form-control" value="{{ old('time') }}" required></div>
                <div class="col-12"><label class="form-label">Meeting link <span class="small text-muted fw-normal">(only for Google Meet / Zoom / Teams — Portal Meeting joins inside the dashboard)</span></label><input type="url" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://meet.google.com/…"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control" maxlength="1000">{{ old('notes') }}</textarea></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Meeting Request</button>
            </div>
        </form>
    </div>
</div>
@if ($errors->any() && old('customer_name'))
    @push('scripts')<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('assignModal')).show();</script>@endpush
@endif
@endsection
