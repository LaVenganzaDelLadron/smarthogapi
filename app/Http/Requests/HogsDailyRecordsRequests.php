<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HogsDailyRecordsRequests extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'hog_id' => ['required', 'integer', 'exists:hogs,id'],
            'hog_pen_id' => ['required', 'integer', 'exists:hog_pens,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'feed_consumed' => ['nullable', 'numeric', 'min:0'],
            'health_status' => ['nullable', 'string', 'max:50'],
            'temperature' => ['nullable', 'numeric'],
            'activity_level' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'recorded_date' => ['required', 'date'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return collect($rules)
                ->map(fn (array $rule) => array_merge(['sometimes'], $rule))
                ->all();
        }

        return $rules;
    }
}
