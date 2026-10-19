<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignWorkRequest extends FormRequest
{
    public function rules(): array
    {
        $teamIds = $this->user()->teamMembers()->pluck('id')->all();

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(config('portal.task_categories')))],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'target_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'unit' => ['required', 'string', 'max:30'],
            'assigned_date' => ['required', 'date', 'after_or_equal:today'],
            'deadline_at' => ['nullable', 'date', 'after_or_equal:assigned_date'],
            'is_mandatory' => ['boolean'],
            'rules_text' => ['nullable', 'string', 'max:2000'],
            'related_list' => ['nullable', 'string', 'max:150'],
            'assignees' => ['required', 'array', 'min:1'],
            'assignees.*' => ['integer', Rule::in($teamIds)],
        ];
    }

    public function messages(): array
    {
        return [
            'assignees.required' => 'Select at least one team member.',
            'assignees.*.in' => 'You can only assign work to your own team.',
        ];
    }

    /** Task attributes, with the rules textarea split into a list. */
    public function taskData(): array
    {
        $data = $this->safe()->except(['assignees', 'rules_text']);
        $data['is_mandatory'] = $this->boolean('is_mandatory');
        $data['rules'] = collect(preg_split('/\r?\n/', (string) $this->input('rules_text')))
            ->map(fn ($line) => trim($line))->filter()->values()->all();

        return $data;
    }
}
