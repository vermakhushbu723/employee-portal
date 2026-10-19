<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'digits:10', Rule::unique('leads', 'mobile')],
            'alternate_mobile' => ['nullable', 'digits:10', 'different:mobile'],
            'email' => ['nullable', 'email', 'max:150'],
            'state' => ['nullable', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:60'],
            'source' => ['required', Rule::in(config('portal.lead.sources'))],
            'interest' => ['required', Rule::in(config('portal.lead.interests'))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return ['mobile.unique' => 'A lead with this mobile number already exists (duplicate lead).'];
    }

    public function attributes(): array
    {
        return ['name' => 'full name', 'interest' => 'interest'];
    }
}
