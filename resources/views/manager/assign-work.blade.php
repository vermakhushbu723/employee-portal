@extends('layouts.app')

@section('title', 'Assign Work')

@section('content')
<form method="POST" action="{{ route('manager.assign-work.store') }}" class="row g-3" novalidate>
    @csrf
    <div class="col-lg-8">
        <div class="card-soft card-pad">
            <h3 class="section-title mb-3">Task Details</h3>

            <div class="mb-3">
                <label class="form-label required">Task Title</label>
                <input name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" placeholder="e.g. Outbound Calling">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label required">Category</label>
                    <select name="category" class="form-select @error('category') is-invalid @enderror">
                        @foreach ($categories as $key => $meta)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Priority</label>
                    <select name="priority" class="form-select">
                        @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label required">Target</label>
                    <input type="number" name="target_quantity" min="1" value="{{ old('target_quantity', 10) }}" class="form-control @error('target_quantity') is-invalid @enderror">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label required">Unit</label>
                    <input name="unit" value="{{ old('unit', 'Calls') }}" class="form-control @error('unit') is-invalid @enderror">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label required">Assigned Date</label>
                    <input type="date" name="assigned_date" value="{{ old('assigned_date', today()->toDateString()) }}" class="form-control @error('assigned_date') is-invalid @enderror">
                    @error('assigned_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deadline</label>
                    <input type="datetime-local" name="deadline_at" value="{{ old('deadline_at') }}" class="form-control @error('deadline_at') is-invalid @enderror">
                    @error('deadline_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Related List</label>
                    <input name="related_list" value="{{ old('related_list') }}" class="form-control" placeholder="e.g. Assigned Leads (25)">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Mandatory Rules <span class="text-muted fw-normal">(one per line)</span></label>
                <textarea name="rules_text" rows="3" class="form-control" placeholder="Minimum 20 calls required.">{{ old('rules_text') }}</textarea>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_mandatory" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="is_mandatory" name="is_mandatory" value="1" @checked(old('is_mandatory'))>
                <label class="form-check-label" for="is_mandatory">Mandatory — blocks normal checkout until submitted</label>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft card-pad">
            <h3 class="section-title mb-2">Assign To</h3>
            @error('assignees')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            @error('assignees.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            @forelse ($team as $member)
                <label class="list-row" style="cursor:pointer">
                    <input class="form-check-input m-0" type="checkbox" name="assignees[]" value="{{ $member->id }}" @checked(in_array($member->id, old('assignees', [])))>
                    <span class="avatar tone-primary" style="width:34px;height:34px;font-size:.75rem">{{ $member->initials }}</span>
                    <span class="min-w-0">
                        <span class="d-block fw-600 small">{{ $member->name }}</span>
                        <span class="d-block small-xs text-muted">{{ $member->employee_code }} · {{ $member->employee?->designation }}</span>
                    </span>
                </label>
            @empty
                <x-empty-state icon="people" title="No team members" />
            @endforelse
            <button class="btn btn-primary btn-lg w-100 mt-3" @disabled($team->isEmpty())><i class="bi bi-send me-1"></i>Assign Work</button>
        </div>
    </div>
</form>
@endsection
