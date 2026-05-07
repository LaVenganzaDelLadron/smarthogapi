<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedingLogsRequests extends FormRequest
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
     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'feeder_id' => ['required', 'exists:feeders,id'],
            'pen_id' => ['required', 'exists:hog_pens,id'],
            'feed_amount_given' => ['required', 'numeric', 'min:0'],
            'triggered' => ['required', 'string'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'feeder_id' => ['sometimes', 'required', 'exists:feeders,id'],
                'pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'feed_amount_given' => ['sometimes', 'required', 'numeric', 'min:0'],
                'triggered' => ['sometimes', 'required', 'string'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'feeder_id.required' => 'The feeder ID is required.',
            'feeder_id.exists' => 'The selected feeder does not exist.',
            'pen_id.required' => 'The pen ID is required.',
            'pen_id.exists' => 'The selected pen does not exist.',
            'feed_amount_given.required' => 'The feed amount is required.',
            'feed_amount_given.numeric' => 'The feed amount must be numeric.',
            'feed_amount_given.min' => 'The feed amount must be at least 0.',
            'triggered.required' => 'The triggered field is required.',
        ];
    }
}
