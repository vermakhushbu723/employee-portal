@extends('layouts.app')

@section('title', 'Roles')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('admin.roles.store') }}" class="card-soft card-pad">
            @csrf
            <h3 class="section-title mb-3"><i class="bi bi-plus-circle me-1"></i>Create Role</h3>
            <div class="mb-3">
                <label class="form-label required">Role name</label>
                <input name="name" value="{{ old('name') }}" maxlength="60" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Recruiter, Senior Telecaller">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Access (which portal it opens)</label>
                <select name="base_role" class="form-select">
                    @foreach ($baseRoles as $r)
                        <option value="{{ $r->value }}" @selected(old('base_role', 'employee') === $r->value)>{{ $r->label() }} — {{ ucfirst($r->portal()) }} portal</option>
                    @endforeach
                </select>
                <div class="form-text">The role gets the same pages and rules as this access level.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <input name="description" value="{{ old('description') }}" maxlength="255" class="form-control" placeholder="Optional">
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Create Role</button>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card-soft">
            <div class="card-pad pb-2"><h3 class="section-title">Roles</h3></div>
            <div class="table-responsive">
                <table class="table table-soft">
                    <thead><tr><th>Role</th><th>Access</th><th class="text-end">Employees</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($baseRoles as $r)
                            <tr class="text-muted"><td class="fw-600">{{ $r->label() }} <x-badge tone="secondary">built-in</x-badge></td><td>{{ ucfirst($r->portal()) }} portal</td><td class="text-end">{{ \App\Models\User::where('role', $r->value)->whereNull('custom_role_id')->count() }}</td><td></td></tr>
                        @endforeach
                        @foreach ($roles as $role)
                            <tr>
                                <td><div class="fw-600">{{ $role->name }}</div><div class="small-xs text-muted">{{ $role->description }}</div></td>
                                <td>{{ $role->base_role->label() }} · {{ ucfirst($role->base_role->portal()) }} portal</td>
                                <td class="text-end">{{ $role->users_count }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Delete role {{ $role->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" @disabled($role->users_count > 0)><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
