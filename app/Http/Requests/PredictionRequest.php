<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pen_id' => ['required', 'exists:hog_pens,id'],
            'async' => ['boolean'],
            'use_cache' => ['boolean'],
            'pig_age_days' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'avg_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'pen_capacity' => ['nullable', 'integer', 'min:1'],
            'current_feed_kg' => ['nullable', 'numeric', 'min:0'],
            'num_pens' => ['nullable', 'integer', 'min:1'],
            'feed_type' => ['nullable', 'string', 'max:255'],
            'growth_stage' => ['nullable', 'string', 'max:255'],
            'device_code' => ['nullable', 'string', 'max:255'],
            'feeding_times' => ['nullable', 'array'],
            'feeding_times.*' => ['required', 'string', Rule::date()->format('H:i')],
        ];
    }

    public function messages(): array
    {
        return [
            'pen_id.required' => 'A pen ID is required.',
            'pen_id.exists' => 'The selected pen does not exist.',
            'async.boolean' => 'Async must be a boolean value.',
            'use_cache.boolean' => 'Use cache must be a boolean value.',
            'pig_age_days.integer' => 'Pig age must be an integer.',
            'pig_age_days.min' => 'Pig age must be at least 0 days.',
            'avg_weight_kg.numeric' => 'Average weight must be a numeric value.',
            'avg_weight_kg.min' => 'Average weight must be at least 0 kg.',
            'pen_capacity.min' => 'Pen capacity must be at least 1.',
            'current_feed_kg.min' => 'Current feed must be at least 0 kg.',
            'num_pens.min' => 'Number of pens must be at least 1.',
            'feeding_times.array' => 'Feeding times must be provided as an array.',
            'feeding_times.*.date_format' => 'Each feeding time must use the HH:MM format.',
        ];
    }
}
