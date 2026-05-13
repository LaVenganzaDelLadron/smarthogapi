<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
