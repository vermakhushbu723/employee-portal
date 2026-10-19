@extends('layouts.app')

@section('title', 'Create New Lead')
@section('back', route('employee.leads.index'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <form method="POST" action="{{ route('employee.leads.store') }}" class="card-soft card-pad" novalidate>
            @csrf

            <div class="mb-3">
                <label class="form-label required" for="name">Full Name</label>
                <div class="input-icon">
                    <i class="bi bi-person"></i>
                    <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Enter full name" required>
                </div>
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label required" for="mobile">Mobile Number</label>
                    <div class="input-icon">
                        <i class="bi bi-telephone"></i>
                        <input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="numeric" maxlength="10" class="form-control @error('mobile') is-invalid @enderror" placeholder="Enter 10 digit mobile number" required>
                    </div>
                    @error('mobile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="alternate_mobile">Alternate Number</label>
                    <div class="input-icon">
                        <i class="bi bi-telephone-plus"></i>
                        <input id="alternate_mobile" name="alternate_mobile" value="{{ old('alternate_mobile') }}" inputmode="numeric" maxlength="10" class="form-control @error('alternate_mobile') is-invalid @enderror" placeholder="Enter alternate number (optional)">
                    </div>
                    @error('alternate_mobile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email ID</label>
                <div class="input-icon">
                    <i class="bi bi-envelope"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Enter email address (optional)">
                </div>
                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="state">State</label>
                    <input id="state" name="state" value="{{ old('state') }}" class="form-control @error('state') is-invalid @enderror" placeholder="State">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label required" for="city">City</label>
                    <div class="input-icon">
                        <i class="bi bi-geo-alt"></i>
                        <select id="city" name="city" class="form-select @error('city') is-invalid @enderror" required>
                            <option value="">Select City</option>
                            @foreach ($options['cities'] as $city)
                                <option @selected(old('city') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label required" for="source">Source</label>
                    <div class="input-icon">
                        <i class="bi bi-megaphone"></i>
                        <select id="source" name="source" class="form-select @error('source') is-invalid @enderror" required>
                            <option value="">Select Source</option>
                            @foreach ($options['sources'] as $source)
                                <option @selected(old('source') === $source)>{{ $source }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('source')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label required" for="interest">Interest In</label>
                    <div class="input-icon">
                        <i class="bi bi-bag"></i>
                        <select id="interest" name="interest" class="form-select @error('interest') is-invalid @enderror" required>
                            <option value="">Select Service / Product</option>
                            @foreach ($options['interests'] as $interest)
                                <option @selected(old('interest') === $interest)>{{ $interest }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('interest')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="follow_up_date">Follow-up Date</label>
                    <input id="follow_up_date" type="date" name="follow_up_date" value="{{ old('follow_up_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('follow_up_date') is-invalid @enderror">
                    @error('follow_up_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror" placeholder="Add remarks about the customer...">{{ old('remarks') }}</textarea>
                @error('remarks')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100"><i class="bi bi-plus-circle-fill me-2"></i>Save Lead</button>
        </form>
    </div>
</div>
@endsection
