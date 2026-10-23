<div class="table-responsive">
    <table class="table table-soft">
        <thead><tr><th style="width:36px"><input type="checkbox" class="form-check-input" data-toggle-rows="batch_ids[]" aria-label="Select all"></th><th>#</th><th>Upload Date & Time</th><th>File Name</th><th>Manager</th><th class="text-end">Total Records</th><th class="text-end">Valid Records</th><th class="text-end">Invalid Records</th><th class="text-end">Shared</th><th>Uploaded By</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
            @forelse ($batches as $i => $b)
                <tr>
                    <td><input type="checkbox" class="form-check-input" name="batch_ids[]" value="{{ $b->id }}" aria-label="Select"></td>
                    <td class="small text-muted">{{ $i + 1 }}</td>
                    <td class="small text-nowrap">{{ $b->created_at->format('d M Y h:i A') }}</td>
                    <td class="small">{{ $b->file_name }}</td>
                    <td class="small">{{ $b->manager?->name ?? '—' }}</td>
                    <td class="text-end">{{ number_format($b->total_rows) }}</td>
                    <td class="text-end">{{ number_format($b->imported) }}</td>
                    <td class="text-end text-danger">{{ number_format($b->invalid + $b->duplicates) }}</td>
                    <td class="text-end">{{ number_format($b->assigned_count) }}<div class="small-xs text-muted">{{ $b->pool_count }} left</div></td>
                    <td class="small">{{ $b->uploader?->name }}</td>
                    <td><x-badge tone="success">Completed</x-badge></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.data.show', $b) }}" class="btn btn-sm btn-outline-primary">View</a>
                        <div class="dropdown d-inline">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-chevron-down"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
                                <li><a class="dropdown-item rounded-3" href="{{ route('admin.data.index', ['batch' => $b->id]) }}"><i class="bi bi-share me-2"></i>Share / progress</a></li>
                                <li><a class="dropdown-item rounded-3" href="{{ route('admin.data.show', ['batch' => $b, 'assigned' => 'none']) }}"><i class="bi bi-inbox me-2"></i>Unassigned leads</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12"><x-empty-state icon="cloud-upload" title="No uploads in this period" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
