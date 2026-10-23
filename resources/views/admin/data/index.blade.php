@extends('layouts.app')

@section('title', 'Data Upload & Share')

@section('content')
@if ($errors->any())
    <div class="alert alert-danger small">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>{{ $errors->first() }}
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">Data Upload & Share</h2>
        <div class="text-muted small">Upload data and share with selected employees for the chosen department.</div>
    </div>
    <a href="{{ route('admin.data.template') }}" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Download Template</a>
</div>

{{-- ============ Upload & share (steps 1–4) ============ --}}
<form id="uploadForm" method="POST" action="{{ route('admin.data.upload') }}" enctype="multipart/form-data" class="row g-3 mb-3" novalidate>
    @csrf
    <input type="hidden" name="original_name" id="originalName">

    <div class="col-xl-6">
        {{-- 1. Department & manager --}}
        <div class="card-soft card-pad mb-3">
            <div class="step-head"><span class="step-no tone-primary">1</span><div><h3 class="section-title">Select Department & Manager</h3><div class="small text-muted">Choose department for this data</div></div></div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label required">Department</label>
                    <div class="input-icon">
                        <i class="bi bi-building"></i>
                        <select name="department_id" id="deptSelect" class="form-select @error('department_id') is-invalid @enderror" required>
                            <option value="">Select department</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((int) old('department_id') === $dept->id)>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department Manager</label>
                    <div class="input-icon">
                        <i class="bi bi-person-badge"></i>
                        <select name="manager_id" id="mgrSelect" class="form-select">
                            <option value="">All managers</option>
                            @foreach ($managers as $mgr)
                                <option value="{{ $mgr->id }}" data-department="{{ $mgr->employee?->department_id }}" @selected((int) old('manager_id') === $mgr->id)>{{ $mgr->name }} ({{ $mgr->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Employees --}}
        <div class="card-soft card-pad mb-3">
            <div class="step-head"><span class="step-no tone-purple">2</span><div><h3 class="section-title">Select Employees</h3><div class="small text-muted">Choose one or multiple employees to share this data</div></div></div>
            <div class="d-flex gap-2 mb-2">
                <div class="input-icon flex-grow-1">
                    <i class="bi bi-search"></i>
                    <input type="search" id="empSearch" class="form-control" placeholder="Search employee by name or ID...">
                </div>
                <span class="badge-soft tone-primary align-self-center px-3 py-2"><span id="empCount">0</span>&nbsp;selected</span>
            </div>
            <label class="emp-row emp-all"><input type="checkbox" class="form-check-input m-0" id="empAll"> <span class="small fw-600">Select all shown</span> <span class="small text-muted ms-auto" id="empNote"></span></label>
            <div class="emp-list" id="empList">
                @foreach ($staff as $emp)
                    <label class="emp-row" data-manager="{{ $emp->employee?->manager_id }}" data-department="{{ $emp->employee?->department_id }}" data-search="{{ strtolower($emp->name.' '.$emp->employee_code) }}">
                        <input type="checkbox" class="form-check-input m-0" name="users[]" value="{{ $emp->id }}" @disabled(! $emp->isActive()) @checked(in_array($emp->id, old('users', [])))>
                        <span class="avatar tone-primary" style="width:30px;height:30px;font-size:.66rem">{{ $emp->initials }}</span>
                        <span class="fw-600 small emp-name">{{ $emp->name }}</span>
                        <span class="small text-muted emp-code">{{ $emp->employee_code }}</span>
                        <span class="small emp-dept">{{ $emp->employee?->department?->name ?? '—' }}</span>
                        <x-badge :tone="$emp->isActive() ? 'success' : 'secondary'">{{ $emp->isActive() ? 'Active' : 'Inactive' }}</x-badge>
                    </label>
                @endforeach
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-2 small">
                <span class="fw-600">Share:</span>
                <div class="form-check m-0"><input class="form-check-input" type="radio" name="mode" id="upEqual" value="equal" checked><label class="form-check-label" for="upEqual">Split equally</label></div>
                <div class="form-check m-0"><input class="form-check-input" type="radio" name="mode" id="upEach" value="each"><label class="form-check-label" for="upEach">Fixed count each</label></div>
                <input type="number" name="count" min="0" max="5000" class="form-control form-control-sm" style="width:130px" placeholder="Count (optional)">
            </div>
        </div>

        {{-- 3. Data fields --}}
        <div class="card-soft card-pad mb-3">
            <div class="step-head">
                <span class="step-no tone-warning">3</span>
                <div class="flex-grow-1"><h3 class="section-title">Select Data Fields</h3><div class="small text-muted">Choose which columns to upload and share</div></div>
                <label class="small fw-600 d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input m-0" id="fieldsAll" checked> Select All</label>
            </div>
            <div class="field-grid" id="fieldGrid">
                <div class="small text-muted"><i class="bi bi-arrow-right-circle"></i> Choose a file in step 4 — its columns will appear here.</div>
            </div>
            <div class="small text-muted mt-2"><span id="fieldCount">0</span> of <span id="fieldTotal">0</span> field(s) selected</div>
        </div>

        <button type="submit" class="btn btn-success btn-lg w-100" id="uploadBtn"><i class="bi bi-upload me-1"></i>Upload & Share Data</button>
    </div>

    <div class="col-xl-6">
        {{-- 4. File --}}
        <div class="card-soft card-pad mb-3">
            <div class="step-head"><span class="step-no tone-success">4</span><div><h3 class="section-title">Upload File</h3><div class="small text-muted">Upload your Excel or CSV file with selected fields</div></div></div>
            <label class="dropzone" id="dropzone" for="fileInput">
                <i class="bi bi-cloud-arrow-up"></i>
                <span class="fw-600" id="dropText">Drag & drop your file here</span>
                <span class="small text-muted">or</span>
                <span class="btn btn-sm btn-outline-primary">Choose File</span>
                <span class="small text-muted">Supported formats: Excel (.xlsx, .xls) or CSV (.csv) · Max 10 MB · Max 5,000 rows</span>
                <input type="file" name="file" id="fileInput" accept=".csv,.xlsx,.xls,.txt" class="d-none" required>
            </label>
            <div class="mt-2">
                <label class="form-label">Data name <span class="text-muted fw-normal">(optional)</span></label>
                <input name="name" value="{{ old('name') }}" maxlength="100" class="form-control" placeholder="e.g. Credit Card Leads 23 Oct">
            </div>
        </div>

        {{-- Preview --}}
        <div class="card-soft card-pad mb-3">
            <div class="section-head">
                <div><h3 class="section-title">Selected Fields Preview</h3><div class="small text-muted">These columns will be uploaded and shared</div></div>
                <span class="small text-muted">Total Columns: <b id="chipCount">0</b></span>
            </div>
            <div class="d-flex flex-wrap gap-1 mb-2" id="fieldChips"></div>
            <div class="table-responsive" id="previewWrap">
                <table class="table table-soft table-sm mb-0" id="previewTable"><tbody><tr><td class="text-muted small">No file selected yet.</td></tr></tbody></table>
            </div>
        </div>

        <div class="rule-box">
            <h6><i class="bi bi-info-circle me-1"></i>Note:</h6>
            <ul class="small">
                <li>Only selected columns will be uploaded and shared with chosen employees.</li>
                <li>The <b>Mobile Number</b> column is required. Duplicate numbers (already in the portal) and invalid numbers are skipped automatically.</li>
                <li>Make sure the file contains correct data as per selected department.</li>
            </ul>
        </div>
    </div>
</form>

{{-- ============ Live numbers ============ --}}
<div data-live-region="data">
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg"><x-stat-tile icon="cloud-upload" label="Total Uploaded" tone="primary" :value="number_format($stats['total'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="inbox" label="Unassigned (Pool)" tone="warning" :value="number_format($stats['pool'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="share" label="Shared" tone="info" :value="number_format($stats['assigned'])" /></div>
        <div class="col-6 col-lg"><x-stat-tile icon="telephone-outbound" label="Called / Worked" tone="success" :value="number_format($stats['worked'])" /></div>
        <div class="col-12 col-lg"><x-stat-tile icon="trophy" label="Converted" tone="purple" :value="number_format($stats['converted'])" /></div>
    </div>
</div>

{{-- ============ Share data that is still unassigned ============ --}}
<form method="POST" action="{{ route('admin.data.share') }}" class="card-soft card-pad mb-3">
    @csrf
    <div class="section-head">
        <h3 class="section-title"><i class="bi bi-share me-1"></i>Share Unassigned Data</h3>
        <span class="small text-muted">{{ number_format($stats['pool']) }} unassigned</span>
    </div>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Which data</label>
            <select name="batch_id" class="form-select">
                <option value="">All unassigned data</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected($batchId === $batch->id) @disabled($batch->pool_count === 0)>{{ $batch->name }} — {{ $batch->pool_count }} unassigned</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">How</label>
            <select name="mode" class="form-select"><option value="equal">Split equally</option><option value="each">Fixed count each</option></select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Count</label>
            <input type="number" name="count" min="0" max="5000" class="form-control" placeholder="blank = all">
        </div>
        <div class="col-md-3">
            <button class="btn btn-success w-100" @disabled($stats['pool'] === 0)><i class="bi bi-send me-1"></i>Share</button>
        </div>
    </div>
    <div class="row g-1 mt-2" style="max-height: 140px; overflow-y: auto">
        @foreach ($recipients as $emp)
            <div class="col-md-4 col-lg-3">
                <label class="d-flex align-items-center gap-2 small" style="cursor:pointer">
                    <input class="form-check-input m-0" type="checkbox" name="users[]" value="{{ $emp->id }}"> {{ $emp->name }} <span class="text-muted">{{ $emp->employee_code }}</span>
                </label>
            </div>
        @endforeach
    </div>
</form>

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
                            <td><div class="fw-600">{{ $row['user']->name }}</div><div class="small-xs text-muted">{{ $row['user']->employee_code }}</div></td>
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
                                        <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-arrow-counterclockwise"></i> Take back</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="share" title="No data shared yet" text="Upload a file and share it with your team." /></td></tr>
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
                <thead><tr><th>Name</th><th>Department</th><th>File</th><th class="text-end">Fields</th><th class="text-end">Imported</th><th class="text-end">Skipped</th><th class="text-end">Unassigned</th><th class="text-end">Shared</th><th class="text-end">Called</th><th>Uploaded</th><th></th></tr></thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td class="fw-600">{{ $batch->name }}</td>
                            <td class="small">{{ $batch->department?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $batch->file_name }}</td>
                            <td class="text-end">{{ count($batch->columns ?? []) ?: '—' }}</td>
                            <td class="text-end">{{ $batch->imported }}</td>
                            <td class="text-end small text-muted" title="duplicate / invalid">{{ $batch->duplicates }} / {{ $batch->invalid }}</td>
                            <td class="text-end text-warning fw-600">{{ $batch->pool_count }}</td>
                            <td class="text-end">{{ $batch->assigned_count }}</td>
                            <td class="text-end">{{ $batch->worked_count }}</td>
                            <td class="small text-nowrap">{{ $batch->created_at->format('d M, h:i A') }}<div class="small-xs text-muted">{{ $batch->uploader?->name }}</div></td>
                            <td class="text-end"><a href="{{ route('admin.data.show', $batch) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-empty-state icon="cloud-upload" title="No data uploaded yet" text="Download the template, fill it and upload." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="{{ asset('js/data-upload.js') }}?v={{ filemtime(public_path('js/data-upload.js')) }}"></script>
@endpush
