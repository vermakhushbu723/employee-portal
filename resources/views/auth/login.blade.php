@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="card-soft p-4 p-md-5">
        <div class="text-center mb-4">
            <img src="{{ asset('images/logo.png') }}" alt="Employee Portal — Work Together. Grow Together." class="auth-brand-logo" width="640" height="517">
            <h1 class="visually-hidden">Employee Portal</h1>
            <p class="text-muted mb-0">Sign in with your Employee ID</p>
        </div>

        @if (session('status'))
            <div class="alert alert-info small">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input type="text" name="employee_code" value="{{ old('employee_code') }}"
                           class="form-control @error('employee_code') is-invalid @enderror"
                           placeholder="Employee ID" autocomplete="username" autofocus required>
                </div>
                @error('employee_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Password" autocomplete="current-password" required>
                    <button type="button" class="btn-icon input-action" data-toggle-password="password" aria-label="Show password">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4 small">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">Remember Me</label>
                </div>
                <a href="{{ route('password.request') }}">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
        </form>

        <div class="divider-or my-3">OR</div>

        <button type="button" class="btn btn-outline-secondary btn-lg w-100" disabled title="OTP login can be enabled by the admin">
            <i class="bi bi-phone me-1"></i> Login with OTP
        </button>

        @if (app()->environment('local'))
            <div class="info-box mt-4">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Demo logins (password <b>password</b>):<br>
                    Employee <b>EMP001</b> · Manager <b>MGR001</b> · Admin <b>ADM001</b>
                </div>
            </div>
        @endif
    </div>

    <p class="text-center fw-600 mt-4 mb-0" style="color: var(--sage)">Work Together. Grow Together.</p>
@endsection
