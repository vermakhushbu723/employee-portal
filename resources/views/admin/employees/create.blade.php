@extends('layouts.app')

@section('title', 'Add Employee')
@section('back', route('admin.employees.index'))

@section('content')
<form method="POST" action="{{ route('admin.employees.store') }}" class="row g-3" novalidate>
    @csrf
    @include('admin.employees.partials.form')

    <div class="col-12">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Create Account</button>
    </div>
</form>
@endsection
