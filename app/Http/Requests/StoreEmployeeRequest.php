<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('users', 'employee_code')],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'mobile' => ['nullable', 'digits:10'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', Password::min(8)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'designation' => ['nullable', 'string', 'max:100'],
            'work_type' => ['required', Rule::in(['work_from_home', 'office', 'field'])],
            'training_from' => ['nullable', 'date', 'required_with:training_to'],
            'training_to' => ['nullable', 'date', 'required_with:training_from', 'after_or_equal:training_from'],
            'joining_date' => ['nullable', 'date', Rule::when($this->filled('training_to'), 'after_or_equal:training_to')],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'training_to.after_or_equal' => 'Training end date must be on or after the start date.',
            'joining_date.after_or_equal' => 'Joining date must be on or after the training end date.',
        ];
    }

    public function attributes(): array
    {
        return ['training_from' => 'training start date', 'training_to' => 'training end date'];
    }
}
