<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DailyFarmReportsRequests extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'report_date' => ['required', 'date'],
            'total_feed_consumed' => ['nullable', 'numeric', 'min:0'],
            'total_hogs' => ['nullable', 'integer', 'min:0'],
            'avg_weight' => ['nullable', 'numeric', 'min:0'],
            'mortality_count' => ['nullable', 'numeric', 'min:0'],
            'active_pens' => ['nullable', 'integer', 'min:0'],
            'avg_temperature' => ['nullable', 'numeric'],
            'avg_humidity' => ['nullable', 'numeric', 'between:0,100'],
            'alerts_triggered' => ['nullable', 'integer', 'min:0'],
            'sick_hogs' => ['nullable', 'integer', 'min:0'],
            'avg_weekly_weight_gain' => ['nullable', 'numeric'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return collect($rules)
                ->map(fn (array $rule) => array_merge(['sometimes'], $rule))
                ->all();
        }

        return $rules;
    }
}
