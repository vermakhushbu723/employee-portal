{{-- Live employee table. $rows from LiveStatusService::rows(), $portal = admin | manager. Filtered in the browser by [data-live-filter]. --}}
@use('App\Services\LiveStatusService')
@use('App\Support\Duration')

<div class="table-responsive">
    <table class="table table-soft align-middle live-table mb-0">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Team</th>
                <th>Status</th>
                <th>Login</th>
                <th>Working Time</th>
                <th class="text-center">Calls<br><span class="small-xs text-muted">Done / Target</span></th>
                <th class="text-center">Interested</th>
                <th class="text-center">Meetings</th>
                <th style="min-width:120px">Progress</th>
                <th>Battery</th>
                <th>Network</th>
                <th>Location</th>
                <th>Last Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php
                    $u = $r['user'];
                    [$batIcon, $batTone] = LiveStatusService::batteryIcon($u->battery_level, $u->battery_charging);
                    $team = $u->employee?->department?->name ?? '—';
                    $fresh = $u->location_at && $u->location_at->gt(now()->subMinutes(15));
                @endphp
                <tr data-state="{{ in_array($r['state'], ['offline', 'out'], true) ? 'offline' : ($r['state'] === 'break' ? 'idle' : $r['state']) }}"
                    data-team="{{ $team }}" data-search="{{ strtolower($u->name.' '.$u->employee_code) }}">
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar tone-{{ $r['tone'] }}" style="width:34px;height:34px;font-size:.72rem">{{ $u->initials }}</span>
                            <div class="min-w-0">
                                <div class="fw-600 text-nowrap">{{ $u->name }}</div>
                                <div class="small-xs text-muted">{{ $u->employee_code }}@if ($u->device_type) · <i class="bi bi-{{ $u->device_type === 'mobile' ? 'phone' : 'laptop' }}"></i>@endif</div>
                            </div>
                        </div>
                    </td>
                    <td class="small text-nowrap">{{ $team }}</td>
                    <td><x-badge :tone="$r['tone']">{{ $r['label'] }}</x-badge></td>
                    <td class="small text-nowrap">{{ $r['login']?->format('h:i A') ?? '--' }}</td>
                    <td class="small fw-600 font-monospace">{{ Duration::hms($r['active']) }}</td>
                    <td class="text-center small"><b>{{ $r['calls'] }}</b> / {{ $r['calls_target'] }}</td>
                    <td class="text-center small fw-600">{{ $r['interested'] }}</td>
                    <td class="text-center small">{{ $r['meetings'] }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <x-progress class="flex-grow-1" :value="$r['progress']" :tone="$r['progress'] >= 75 ? 'success' : ($r['progress'] >= 40 ? 'warning' : 'danger')" />
                            <span class="small fw-bold">{{ $r['progress'] }}%</span>
                        </div>
                    </td>
                    <td class="small text-nowrap">
                        @if ($u->battery_level !== null && $u->device_at?->isToday())
                            <span class="text-{{ $batTone }}"><i class="bi bi-{{ $batIcon }}"></i> {{ $u->battery_level }}%</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">
                        @if ($net = LiveStatusService::network($u))
                            <span class="net-bars text-{{ $net[1] }}" title="{{ $net[3] }}">@for ($b = 1; $b <= 4; $b++)<i @class(['on' => $b <= $net[0]])></i>@endfor</span>
                            {{ $net[2] }}<div class="small-xs text-muted">{{ $net[3] }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">
                        @if ($u->latitude && $u->location_at?->isToday())
                            <a href="https://www.google.com/maps?q={{ $u->latitude }},{{ $u->longitude }}" target="_blank" rel="noopener" class="{{ $fresh ? '' : 'text-muted' }}" title="Updated {{ $u->location_at->diffForHumans() }} · ±{{ $u->location_accuracy }} m">
                                <i class="bi bi-geo-alt-fill"></i> Map
                            </a>
                        @elseif ($u->location_status === 'denied')
                            <span class="text-danger" title="Employee blocked location access"><i class="bi bi-geo-alt"></i> Off</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap {{ $r['state'] === 'working' ? 'text-success fw-600' : 'text-muted' }}">
                        {{ $r['state'] === 'working' ? 'Live now' : ($r['last_seen'] ? $r['last_seen']->diffForHumans(null, true, true).' ago' : '—') }}
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route($portal.'.reports.daily', ['q' => $u->employee_code]) }}"><i class="bi bi-file-earmark-text me-2"></i>Daily report</a></li>
                                @if ($r['session'])
                                    <li><a class="dropdown-item" href="{{ route($portal.'.corrections.create', $r['session']) }}"><i class="bi bi-pencil-square me-2"></i>Correct work data</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route($portal.'.tracking', ['user' => $u->id]) }}"><i class="bi bi-geo-alt me-2"></i>Live tracking</a></li>
                                @if ($portal === 'admin')
                                    <li><a class="dropdown-item" href="{{ route('admin.employees.edit', $u) }}"><i class="bi bi-person-gear me-2"></i>Edit employee</a></li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="15"><x-empty-state icon="people" title="No employees found" /></td></tr>
            @endforelse
            <tr class="live-no-match d-none"><td colspan="15" class="text-center text-muted small py-4">No employee matches this filter.</td></tr>
        </tbody>
    </table>
</div>
