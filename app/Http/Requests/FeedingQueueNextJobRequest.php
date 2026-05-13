<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedingQueueNextJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feeder_id' => ['required', 'integer', 'exists:feeders,id'],
            'max_jobs' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'feeder_id.required' => 'A feeder ID is required.',
            'feeder_id.integer' => 'The feeder ID must be an integer.',
            'feeder_id.exists' => 'The selected feeder does not exist.',
            'max_jobs.integer' => 'Max jobs must be an integer.',
            'max_jobs.min' => 'Max jobs must be at least 1.',
            'max_jobs.max' => 'Max jobs may not exceed 10.',
        ];
    }
}
