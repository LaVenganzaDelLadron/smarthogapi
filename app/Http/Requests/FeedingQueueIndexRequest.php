<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedingQueueIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,processing,completed,skipped,error'],
            'feeder_id' => ['nullable', 'integer', 'exists:feeders,id'],
            'date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of pending, processing, completed, skipped, or error.',
            'feeder_id.integer' => 'Feeder ID must be an integer.',
            'feeder_id.exists' => 'The selected feeder does not exist.',
            'date.date' => 'Date must be a valid date.',
        ];
    }
}
