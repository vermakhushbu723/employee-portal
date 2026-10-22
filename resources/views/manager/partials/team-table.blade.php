@use('App\Services\TeamMonitorService')
@use('App\Support\Duration')

{{-- Phones: one card per employee --}}
<div class="d-lg-none px-3 pb-2">
    @forelse ($team as $row)
        @php
            [$label, $tone] = TeamMonitorService::badge($row['state']);
            $t = $row['timer'];
        @endphp
        <div class="list-row align-items-start">
            <span class="avatar tone-{{ $tone }}">{{ $row['user']->initials }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <div class="list-title text-truncate">{{ $row['user']->name }}</div>
                        <div class="small-xs text-muted">{{ $row['user']->employee_code }} · Login {{ $t['check_in_at'] ?? '—' }}</div>
                    </div>
                    <x-badge :tone="$tone">{{ $label }}</x-badge>
                </div>
                <div class="d-flex justify-content-between small mt-2">
                    <span>Worked <b>{{ Duration::hm($t['worked']) }}</b></span>
                    <span>Active <b>{{ Duration::hm($t['active']) }}</b></span>
                    <span>Break <b>{{ Duration::hm($t['break_used']) }}</b></span>
                    <span class="text-danger">Left <b>{{ $t['checked_in'] ? Duration::hm($t['remaining']) : '—' }}</b></span>
                </div>
                <x-progress class="mt-2" :value="$t['progress']" tone="success" />
            </div>
        </div>
    @empty
        <x-empty-state icon="people" title="No team members assigned" text="Ask the admin to assign employees to you." />
    @endforelse
</div>

{{-- Desktop: table --}}
<div class="table-responsive d-none d-lg-block">
    <table class="table table-soft align-middle">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Login</th>
                <th>Worked</th>
                <th>Active</th>
                <th>Break</th>
                <th>Remaining</th>
                <th style="min-width:140px">Progress</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($team as $row)
                @php
                    [$label, $tone] = TeamMonitorService::badge($row['state']);
                    $t = $row['timer'];
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar tone-primary" style="width:36px;height:36px;font-size:.8rem">{{ $row['user']->initials }}</span>
                            <div>
                                <div class="fw-600">{{ $row['user']->name }}</div>
                                <div class="small-xs text-muted">{{ $row['user']->employee_code }} · {{ $row['user']->employee?->designation }}</div>
                            </div>
                        </div>
                    </td>
                    <td><x-badge :tone="$tone">{{ $label }}</x-badge> @if ($t['is_late'])<x-badge tone="danger">Late</x-badge>@endif</td>
                    <td>{{ $t['check_in_at'] ?? '—' }}</td>
                    <td class="fw-600">{{ Duration::hm($t['worked']) }}</td>
                    <td class="text-success fw-600">{{ Duration::hm($t['active']) }}</td>
                    <td>{{ Duration::hm($t['break_used']) }}</td>
                    <td class="text-danger">{{ $t['checked_in'] ? Duration::hm($t['remaining']) : '—' }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <x-progress class="flex-grow-1" :value="$t['progress']" tone="success" />
                            <span class="small fw-600">{{ $t['progress'] }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="people" title="No team members assigned" text="Ask the admin to assign employees to you." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
