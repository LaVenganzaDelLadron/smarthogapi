<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchPredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pen_ids' => ['required', 'array', 'min:1'],
            'pen_ids.*' => ['required', 'integer', 'exists:hog_pens,id'],
            'async' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'pen_ids.required' => 'At least one pen ID is required.',
            'pen_ids.array' => 'Pen IDs must be provided as an array.',
            'pen_ids.min' => 'At least one pen ID must be included.',
            'pen_ids.*.required' => 'Each pen ID is required.',
            'pen_ids.*.integer' => 'Each pen ID must be an integer.',
            'pen_ids.*.exists' => 'One or more selected pens do not exist.',
            'async.boolean' => 'Async must be a boolean value.',
        ];
    }
}
