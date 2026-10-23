@extends('layouts.app')

@section('title', 'Daily Targets')

@section('content')
<div class="info-box mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <div>Work score = average of <b>active time</b> (shift hours), <b>leads created</b> and <b>interested / mature leads</b> against these daily targets.
        100% = Full Day, 50% = Half Day, 25% = Quarter Day. Default: {{ $defaults['leads'] }} leads + {{ $defaults['interested'] }} interested.</div>
</div>

<form method="POST" action="{{ route('manager.targets.update') }}" class="card-soft">
    @csrf
    <div class="table-responsive">
        <table class="table table-soft">
            <thead><tr><th>Employee</th><th style="width:200px">Leads to create / day</th><th style="width:200px">Interested / Mature / day</th></tr></thead>
            <tbody>
                @forelse ($team as $row)
                    <tr>
                        <td><div class="fw-600">{{ $row['user']->name }}</div><div class="small-xs text-muted">{{ $row['user']->employee_code }}</div></td>
                        <td><input type="number" name="targets[{{ $row['user']->id }}][leads]" value="{{ $row['targets']['leads'] }}" min="0" max="1000" class="form-control form-control-sm"></td>
                        <td><input type="number" name="targets[{{ $row['user']->id }}][interested]" value="{{ $row['targets']['interested'] }}" min="0" max="1000" class="form-control form-control-sm"></td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty-state icon="people" title="No team members assigned" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($team->isNotEmpty())
        <div class="card-pad pt-0"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Targets</button></div>
    @endif
</form>
@endsection
