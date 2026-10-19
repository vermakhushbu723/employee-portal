@extends('layouts.app')

@section('title', 'Employees')

@section('content')
<form method="GET" class="card-soft card-pad mb-3 d-flex flex-wrap gap-2">
    <div class="input-icon flex-grow-1">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, employee ID or mobile">
    </div>
    <select name="role" class="form-select w-auto">
        <option value="">All Roles</option>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-primary">Filter</button>
    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Employee</a>
</form>

<div class="card-soft">
    {{-- Phones: card list --}}
    <div class="d-lg-none px-3">
        @forelse ($users as $user)
            <div class="list-row">
                <span class="avatar tone-{{ $user->isActive() ? 'primary' : 'secondary' }}">{{ $user->initials }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="list-title text-truncate">{{ $user->name }}</div>
                    <div class="small-xs text-muted">{{ $user->employee_code }} · {{ $user->role->label() }}</div>
                    <div class="small-xs text-muted text-truncate">{{ $user->employee?->department?->name ?? '—' }} · Mgr: {{ $user->employee?->manager?->name ?? '—' }}</div>
                </div>
                <div class="text-end">
                    <x-badge :tone="$user->isActive() ? 'success' : 'secondary'">{{ ucfirst($user->status) }}</x-badge>
                    <a href="{{ route('admin.employees.edit', $user) }}" class="btn btn-link btn-sm p-0 d-block mt-1">Edit</a>
                    @unless ($user->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.employees.status', $user) }}" data-confirm="Change status of {{ $user->name }}?" class="mt-1">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-link btn-sm p-0 {{ $user->isActive() ? 'text-danger' : 'text-success' }}">{{ $user->isActive() ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <x-empty-state icon="people" title="No employees found" />
        @endforelse
    </div>

    <div class="table-responsive d-none d-lg-block">
        <table class="table table-soft">
            <thead>
                <tr><th>Employee</th><th>Role</th><th>Department</th><th>Manager</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar tone-primary" style="width:36px;height:36px;font-size:.8rem">{{ $user->initials }}</span>
                                <div>
                                    <div class="fw-600">{{ $user->name }}</div>
                                    <div class="small-xs text-muted">{{ $user->employee_code }} · {{ $user->mobile }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ $user->role->label() }}
                            <div class="small-xs text-muted">{{ ucfirst($user->role->portal()) }} portal</div>
                        </td>
                        <td>{{ $user->employee?->department?->name ?? '—' }}</td>
                        <td>{{ $user->employee?->manager?->name ?? '—' }}</td>
                        <td><x-badge :tone="$user->isActive() ? 'success' : 'secondary'">{{ ucfirst($user->status) }}</x-badge></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.employees.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.employees.status', $user) }}" data-confirm="Change status of {{ $user->name }}?" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm {{ $user->isActive() ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="people" title="No employees found" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-pad pt-0">{{ $users->links() }}</div>
    @endif
</div>
@endsection
