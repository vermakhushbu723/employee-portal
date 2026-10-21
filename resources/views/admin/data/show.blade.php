@extends('layouts.app')

@section('title', $batch->name)
@section('back', route('admin.data.index'))

@section('content')
<div class="card-soft card-pad mb-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div>
        <div class="fw-bold">{{ $batch->name }}</div>
        <div class="small text-muted">{{ $batch->file_name }} · uploaded {{ $batch->created_at->format('d M Y, h:i A') }} by {{ $batch->uploader?->name ?? '—' }}</div>
        <div class="small">{{ $batch->imported }} imported · {{ $batch->duplicates }} duplicate · {{ $batch->invalid }} invalid</div>
    </div>
    <a href="{{ route('admin.data.index', ['batch' => $batch->id]) }}" class="btn btn-success"><i class="bi bi-share me-1"></i>Share this data</a>
</div>

<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2">
    <select name="assigned" class="form-select w-auto">
        <option value="">Everyone</option>
        <option value="none" @selected(request('assigned') === 'none')>Unassigned</option>
        @foreach ($recipients as $emp)
            <option value="{{ $emp->id }}" @selected(request('assigned') == $emp->id)>{{ $emp->name }}</option>
        @endforeach
    </select>
    <select name="status" class="form-select w-auto">
        <option value="">All status</option>
        @foreach (\App\Enums\LeadStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-primary">Filter</button>
</form>

<div class="card-soft" data-live-region="data">
    <div class="table-responsive">
        <table class="table table-soft">
            <thead><tr><th>#</th><th>Customer</th><th>Mobile</th><th>City</th><th>Interest</th><th>Shared with</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td class="small text-muted">{{ $lead->id }}</td>
                        <td class="fw-600">{{ $lead->name }}</td>
                        <td>{{ $lead->mobile }}</td>
                        <td>{{ $lead->city ?? '—' }}</td>
                        <td class="small">{{ $lead->interest }}</td>
                        <td>{{ $lead->assignee?->name ?? '—' }}<div class="small-xs text-muted">{{ $lead->assigned_at?->format('d M, h:i A') }}</div></td>
                        <td><x-badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-badge></td>
                        <td class="small text-muted text-nowrap">{{ $lead->updated_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state icon="inbox" title="No leads match" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($leads->hasPages())
        <div class="card-pad pt-0">{{ $leads->links() }}</div>
    @endif
</div>
@endsection
