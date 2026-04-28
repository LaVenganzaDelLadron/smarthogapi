<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FeedingScheduleRequests extends FormRequest
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
            'hog_pen_id' => ['required', 'exists:hog_pens,id'],
            'mode' => ['required', 'string', 'in:auto,manual,scheduled'],
            'time' => ['required', 'date'],
            'feed_amount' => ['required', 'numeric', 'min:0'],
            'feed_type' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'mode' => ['sometimes', 'required', 'string', 'in:auto,manual,scheduled'],
                'time' => ['sometimes', 'required', 'date'],
                'feed_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
                'feed_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'hog_pen_id.exists' => 'The selected hog pen does not exist.',
            'mode.required' => 'The mode is required.',
            'mode.in' => 'The mode must be auto, manual, or scheduled.',
            'time.required' => 'The time is required.',
            'time.date' => 'The time must be a valid date.',
            'feed_amount.required' => 'The feed amount is required.',
            'feed_amount.min' => 'The feed amount must be at least 0.',
            'feed_type.max' => 'The feed type may not be greater than 50 characters.',
        ];
    }

}
