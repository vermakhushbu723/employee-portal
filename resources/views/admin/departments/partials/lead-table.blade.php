<div class="table-responsive">
    <table class="table table-soft">
        <thead><tr><th>Customer</th><th>Mobile</th><th>City</th><th>Interest</th><th>Shared With</th><th>Status</th><th>File</th><th>Updated</th></tr></thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td class="fw-600">{{ $lead->name }}</td>
                    <td class="text-nowrap">{{ $lead->mobile }}</td>
                    <td class="small">{{ $lead->city ?? '—' }}</td>
                    <td class="small">{{ $lead->interest }}</td>
                    <td class="small text-nowrap">{{ $lead->assignee?->name ?? '—' }}<div class="small-xs text-muted">{{ $lead->assigned_at?->format('d M, h:i A') }}</div></td>
                    <td><x-badge :tone="$lead->status->tone()">{{ $lead->status->label() }}</x-badge></td>
                    <td class="small text-muted">{{ $lead->batch?->file_name }}</td>
                    <td class="small text-muted text-nowrap">{{ $lead->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="inbox" title="No leads here" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if (method_exists($leads, 'hasPages') && $leads->hasPages() && ($paginate ?? true))
    <div class="card-pad pt-0">{{ $leads->links() }}</div>
@endif
