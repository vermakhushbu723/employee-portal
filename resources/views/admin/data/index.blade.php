@extends('layouts.app')

@section('title', 'Data Upload & Share')

@section('content')
@if ($errors->any())
    <div class="alert alert-danger small">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>{{ $errors->first() }}
    </div>
@endif

{{-- Live numbers (refresh by themselves) --}}
<div data-live-region="data">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg"><x-stat-tile icon="cloud-upload" label="Total Uploaded" tone="primary" :value="number_format($stats['total'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="inbox" label="Unassigned (Pool)" tone="warning" :value="number_format($stats['pool'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="share" label="Shared" tone="info" :value="number_format($stats['assigned'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="telephone-outbound" label="Called / Worked" tone="success" :value="number_format($stats['worked'])" /></div>
        <div class="col-12 col-lg"><x-stat-tile icon="trophy" label="Converted" tone="purple" :value="number_format($stats['converted'])" /></div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Upload --}}
    <div class="col-lg-5">
        <form method="POST" action="{{ route('admin.data.upload') }}" enctype="multipart/form-data" class="card-soft card-pad h-100">
            @csrf
            <div class="section-head">
                <h3 class="section-title"><i class="bi bi-cloud-arrow-up me-1"></i>Upload Data</h3>
                <a href="{{ route('admin.data.template') }}" class="small fw-600"><i class="bi bi-download"></i> Sample template</a>
            </div>

            <div class="mb-2">
                <label class="form-label required">Excel / CSV file</label>
                <input type="file" name="file" accept=".csv,.xlsx,.txt" class="form-control @error('file') is-invalid @enderror" required>
                <div class="form-text">Columns: <b>Name, Mobile</b> (required), Alternate Number, Email, City, State, Source, Interest, Remarks. Max 5,000 rows / 5 MB.</div>
            </div>
            <div class="mb-2">
                <label class="form-label required">Data name</label>
                <input name="name" value="{{ old('name', 'Calling Data '.now()->format('d M')) }}" maxlength="100" class="form-control @error('name') is-invalid @enderror">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">Default source</label>
                    <select name="default_source" class="form-select">
                        @foreach (['Data Upload', ...$options['sources']] as $source)
                            <option @selected(old('default_source') === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Default interest</label>
                    <select name="default_interest" class="form-select">
                        @foreach ($options['interests'] as $interest)
                            <option @selected(old('default_interest', 'Other') === $interest)>{{ $interest }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload Data</button>
            <div class="small text-muted mt-2"><i class="bi bi-shield-check"></i> Duplicate numbers (already in the portal) and invalid numbers are skipped automatically.</div>
        </form>
    </div>

    {{-- Share --}}
    <div class="col-lg-7">
        <form method="POST" action="{{ route('admin.data.share') }}" class="card-soft card-pad h-100">
            @csrf
            <div class="section-head">
                <h3 class="section-title"><i class="bi bi-share me-1"></i>Share Data with Employees</h3>
                <span class="small text-muted">{{ number_format($stats['pool']) }} unassigned</span>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label">Which data</label>
                    <select name="batch_id" class="form-select">
                        <option value="">All unassigned data</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch->id }}" @selected((int) old('batch_id', $batchId) === $batch->id) @disabled($batch->pool_count === 0)>
                                {{ $batch->name }} — {{ $batch->pool_count }} unassigned
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">How to share</label>
                    <div class="d-flex gap-3 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeEqual" value="equal" @checked(old('mode', 'equal') === 'equal')>
                            <label class="form-check-label small" for="modeEqual">Split equally</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="modeEach" value="each" @checked(old('mode') === 'each')>
                            <label class="form-check-label small" for="modeEach">Fixed count each</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Number of leads</label>
                <input type="number" name="count" min="0" max="5000" value="{{ old('count') }}" class="form-control @error('count') is-invalid @enderror" placeholder="Split equally: total to share (blank = all)  ·  Fixed count: leads per employee">
            </div>

            <label class="form-label required d-flex justify-content-between">
                <span>Employees</span>
                <a href="#" class="small fw-600" data-check-all="users[]">Select all</a>
            </label>
            <div class="row g-1 mb-3" style="max-height: 180px; overflow-y: auto">
                @forelse ($recipients as $emp)
                    <div class="col-md-6">
                        <label class="d-flex align-items-center gap-2 p-1 rounded-2" style="cursor:pointer">
                            <input class="form-check-input m-0" type="checkbox" name="users[]" value="{{ $emp->id }}" @checked(in_array($emp->id, old('users', [])))>
                            <span class="avatar tone-primary" style="width:28px;height:28px;font-size:.65rem">{{ $emp->initials }}</span>
                            <span class="small"><b>{{ $emp->name }}</b> <span class="text-muted">{{ $emp->employee_code }}</span></span>
                        </label>
                    </div>
                @empty
                    <div class="small text-muted">No active employees yet.</div>
                @endforelse
            </div>
            <button class="btn btn-success w-100" @disabled($stats['pool'] === 0)><i class="bi bi-send me-1"></i>Share Data</button>
        </form>
    </div>
</div>

<div data-live-region="data">
    {{-- Live progress per employee --}}
    <div class="card-soft mb-3">
        <div class="card-pad pb-2 section-head">
            <div>
                <h3 class="section-title">Employee Progress <span class="badge-soft tone-success ms-1"><i class="bi bi-broadcast"></i> Live</span></h3>
                <div class="small text-muted">Updates automatically as employees call and update leads.</div>
            </div>
            <form method="GET" class="d-flex gap-2">
                <select name="batch" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All data</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->id }}" @selected($batchId === $batch->id)>{{ $batch->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-soft">
                <thead>
                    <tr><th>Employee</th><th class="text-end">Shared</th><th class="text-end">Pending call</th><th class="text-end">Called</th><th class="text-end">Interested / Follow-up</th><th class="text-end">Converted</th><th style="min-width:140px">Progress</th><th>Last update</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($distribution as $row)
                        @php $pct = \App\Support\Duration::percent($row['worked'], $row['assigned']); @endphp
                        <tr>
                            <td>
                                <div class="fw-600">{{ $row['user']->name }}</div>
                                <div class="small-xs text-muted">{{ $row['user']->employee_code }}</div>
                            </td>
                            <td class="text-end fw-600">{{ $row['assigned'] }}</td>
                            <td class="text-end text-warning fw-600">{{ $row['pending'] }}</td>
                            <td class="text-end">{{ $row['worked'] }}</td>
                            <td class="text-end">{{ $row['interested'] }}</td>
                            <td class="text-end text-success fw-600">{{ $row['converted'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-progress class="flex-grow-1" :value="$pct" tone="success" />
                                    <span class="small fw-600">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="small text-muted text-nowrap">{{ $row['last_update'] ? \Illuminate\Support\Carbon::parse($row['last_update'])->diffForHumans() : '—' }}</td>
                            <td class="text-end">
                                @if ($row['pending'] > 0)
                                    <form method="POST" action="{{ route('admin.data.recall', $row['user']) }}" data-confirm="Take back {{ $row['pending'] }} un-called lead(s) from {{ $row['user']->name }}?">
                                        @csrf
                                        <input type="hidden" name="batch_id" value="{{ $batchId }}">
                                        <button class="btn btn-sm btn-outline-danger text-nowrap" title="Return un-called leads to the pool"><i class="bi bi-arrow-counterclockwise"></i> Take back</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="share" title="No data shared yet" text="Upload a file, then share it with your team." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Uploaded files --}}
    <div class="card-soft">
        <div class="card-pad pb-2"><h3 class="section-title">Uploaded Data</h3></div>
        <div class="table-responsive">
            <table class="table table-soft">
                <thead><tr><th>Name</th><th>File</th><th class="text-end">Imported</th><th class="text-end">Skipped</th><th class="text-end">Unassigned</th><th class="text-end">Shared</th><th class="text-end">Called</th><th>Uploaded</th><th></th></tr></thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td class="fw-600">{{ $batch->name }}</td>
                            <td class="small text-muted">{{ $batch->file_name }}</td>
                            <td class="text-end">{{ $batch->imported }}</td>
                            <td class="text-end small text-muted" title="duplicate / invalid">{{ $batch->duplicates }} / {{ $batch->invalid }}</td>
                            <td class="text-end text-warning fw-600">{{ $batch->pool_count }}</td>
                            <td class="text-end">{{ $batch->assigned_count }}</td>
                            <td class="text-end">{{ $batch->worked_count }}</td>
                            <td class="small text-nowrap">{{ $batch->created_at->format('d M, h:i A') }}<div class="small-xs text-muted">{{ $batch->uploader?->name }}</div></td>
                            <td class="text-end"><a href="{{ route('admin.data.show', $batch) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="cloud-upload" title="No data uploaded yet" text="Download the sample template, fill it and upload." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
