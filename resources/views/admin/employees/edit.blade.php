@extends('layouts.app')

@section('title', 'Edit Employee')
@section('back', route('admin.employees.index'))

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <span class="avatar tone-primary">{{ $user->initials }}</span>
    <div>
        <div class="fw-bold">{{ $user->name }}</div>
        <div class="small text-muted">{{ $user->employee_code }} · currently {{ $user->roleLabel() }} ({{ ucfirst($user->role->portal()) }} portal)</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.employees.update', $user) }}" class="row g-3" novalidate>
    @csrf
    @method('PUT')
    @include('admin.employees.partials.form', ['user' => $user])

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        <a href="{{ route('admin.employees.index') }}" class="btn btn-light btn-lg">Cancel</a>
    </div>
</form>
@endsection
