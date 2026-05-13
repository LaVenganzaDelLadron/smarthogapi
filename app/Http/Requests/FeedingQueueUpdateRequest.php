<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedingQueueUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:processing,completed,skipped,error'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'actual_feed_time' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'amount_dispensed' => ['nullable', 'numeric', 'min:0'],
            'error_message' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'A status value is required.',
            'status.in' => 'The status must be one of processing, completed, skipped or error.',
            'duration_seconds.integer' => 'Duration must be an integer number of seconds.',
            'duration_seconds.min' => 'Duration must be at least 0 seconds.',
            'actual_feed_time.date_format' => 'The feed time must be in Y-m-d H:i:s format.',
            'amount_dispensed.numeric' => 'Amount dispensed must be numeric.',
            'amount_dispensed.min' => 'Amount dispensed must be at least 0.',
            'error_message.string' => 'Error message must be a string.',
            'error_message.max' => 'Error message may not be greater than 255 characters.',
        ];
    }
}
