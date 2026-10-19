<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['employee_code' => 'Employee ID'];
    }
}
