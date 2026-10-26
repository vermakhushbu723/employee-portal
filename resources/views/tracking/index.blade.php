@extends('layouts.app')

@use('App\Services\LiveStatusService')

@section('title', 'Live Tracking')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Live Tracking</h1>
        <div class="small text-muted">Latest location and battery from each employee's phone / laptop (only while punched in, with their permission).</div>
    </div>
    @if ($selected)
        <a href="{{ route($portal.'.tracking') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-1"></i>Show everyone</a>
    @endif
    <button type="button" class="btn btn-sm btn-light refresh-btn" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-soft overflow-hidden">
            @if ($selected)
                <div class="card-pad py-2 small border-bottom">
                    <b>{{ $selected->name }}</b> ({{ $selected->employee_code }}) — today's trail: {{ $trail->count() }} point(s)
                    @if ($trail->isNotEmpty()) · first {{ $trail->first()[2] }}, last {{ $trail->last()[2] }} @endif
                </div>
            @endif
            <div id="trackMap" class="track-map"
                 data-points='@json($points)' data-trail='@json($trail)' data-selected="{{ $selected?->id }}"
                 data-base="{{ route($portal.'.tracking') }}"></div>
            @if ($points->isEmpty() && $trail->isEmpty())
                <div class="card-pad small text-muted"><i class="bi bi-info-circle"></i> No location received today yet. Employees must allow location access when the portal asks (after punch-in).</div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-soft">
            <div class="card-pad pb-2"><h3 class="section-title mb-0">Employees ({{ $rows->count() }})</h3></div>
            <div class="track-list">
                @foreach ($rows as $r)
                    @php
                        $u = $r['user'];
                        [$batIcon, $batTone] = LiveStatusService::batteryIcon($u->battery_level, $u->battery_charging);
                        $hasLoc = $u->latitude && $u->location_at?->isToday();
                    @endphp
                    <a href="{{ route($portal.'.tracking', ['user' => $u->id]) }}" @class(['list-row list-row-link px-3', 'active' => $selected?->id === $u->id])>
                        <span class="avatar tone-{{ $r['tone'] }}" style="width:34px;height:34px;font-size:.72rem">{{ $u->initials }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="list-title text-truncate">{{ $u->name }}</div>
                            <div class="small-xs text-muted">
                                {{ $r['label'] }} ·
                                @if ($hasLoc) <i class="bi bi-geo-alt-fill text-success"></i> {{ $u->location_at->format('h:i A') }}
                                @elseif ($u->location_status === 'denied') <span class="text-danger"><i class="bi bi-geo-alt"></i> Location off</span>
                                @else no location @endif
                            </div>
                        </div>
                        @if ($u->battery_level !== null && $u->device_at?->isToday())
                            <span class="small text-{{ $batTone }} text-nowrap"><i class="bi bi-{{ $batIcon }}"></i> {{ $u->battery_level }}%</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const el = document.getElementById('trackMap');
    const points = JSON.parse(el.dataset.points);
    const trail = JSON.parse(el.dataset.trail);
    const map = L.map(el, { scrollWheelZoom: false }).setView([22.97, 78.65], 5); // India
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);

    const colors = { success: '#2E7D6B', info: '#3B82C4', warning: '#E9A03B', purple: '#8E5BA0', danger: '#C8553D', secondary: '#8A8178' };
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const bounds = [];

    points.forEach((p) => {
        const m = L.circleMarker([p.lat, p.lng], { radius: 9, color: '#fff', weight: 2, fillColor: colors[p.tone] || '#555', fillOpacity: 1 }).addTo(map);
        m.bindPopup(`<b>${esc(p.name)}</b> (${esc(p.code)})<br>${esc(p.state)} · updated ${esc(p.at)}${p.battery !== null ? `<br>Battery ${p.battery}%` : ''}<br><a href="${el.dataset.base}?user=${p.id}">Today's trail</a> · <a href="https://www.google.com/maps?q=${p.lat},${p.lng}" target="_blank" rel="noopener">Google Maps</a>`);
        if (!el.dataset.selected || String(p.id) === el.dataset.selected) bounds.push([p.lat, p.lng]);
    });

    if (trail.length) {
        const line = trail.map((t) => [t[0], t[1]]);
        L.polyline(line, { color: '#B85C3A', weight: 4, opacity: .8 }).addTo(map);
        trail.forEach((t, i) => {
            if (i === 0 || i === trail.length - 1) {
                L.marker([t[0], t[1]]).addTo(map).bindPopup(`${i === 0 ? 'First' : 'Latest'} point · ${esc(t[2])}${t[3] !== null ? ` · battery ${t[3]}%` : ''}`);
            }
        });
        bounds.push(...line);
    }

    if (bounds.length === 1) map.setView(bounds[0], 15);
    else if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
})();
</script>
@endpush
