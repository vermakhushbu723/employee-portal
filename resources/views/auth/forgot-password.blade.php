@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="card-soft p-4 p-md-5 text-center">
        <img src="{{ asset('images/logo.png') }}" alt="Employee Portal" class="auth-brand-logo" width="640" height="517">
        <h1 class="h4 fw-bold">Forgot your password?</h1>
        <p class="text-muted">
            Employee accounts are managed by HR. Please contact your reporting manager or HR
            with your Employee ID to reset your password.
        </p>
        <a href="{{ route('login') }}" class="btn btn-primary w-100 mt-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Login
        </a>
    </div>
@endsection
