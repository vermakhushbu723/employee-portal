<div class="table-responsive">
    <table class="table table-soft">
        <thead><tr><th>Employee</th><th class="text-end">Shared</th><th class="text-end">Called / Worked</th><th class="text-end">Interested / Follow-up</th><th class="text-end">Converted</th><th class="text-end">Conversion</th><th style="min-width:150px">Work Done</th></tr></thead>
        <tbody>
            @forelse ($employees as $e)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar tone-primary" style="width:32px;height:32px;font-size:.7rem">{{ $e['user']->initials }}</span>
                            <div><div class="fw-600">{{ $e['user']->name }}</div><div class="small-xs text-muted">{{ $e['user']->employee_code }}</div></div>
                        </div>
                    </td>
                    <td class="text-end">{{ number_format($e['shared']) }}</td>
                    <td class="text-end">{{ number_format($e['called']) }}</td>
                    <td class="text-end">{{ number_format($e['interested']) }}</td>
                    <td class="text-end text-success fw-600">{{ number_format($e['converted']) }}</td>
                    <td class="text-end">{{ $e['shared'] ? round($e['converted'] / $e['shared'] * 100, 1) : 0 }}%</td>
                    <td><div class="d-flex align-items-center gap-2"><x-progress class="flex-grow-1" :value="$e['pct']" :tone="$e['pct'] >= 60 ? 'success' : 'warning'" /><span class="small fw-600">{{ $e['pct'] }}%</span></div></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="people" title="No data shared with employees yet" /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
