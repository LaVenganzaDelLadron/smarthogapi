<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FeedersRequests extends FormRequest
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
            'device_id' => ['required', 'exists:iot_devices,id'],
            'status' => ['required', 'string', 'in:active, inactive, maintenance'],
            'last_refill' => ['nullable', 'date'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'device_id' => ['sometimes', 'required', 'exists:iot_devices,id'],
                'status' => ['sometimes', 'required', 'string', 'in:active, inactive, maintenance'],
                'last_refill' => ['sometimes', 'nullable', 'date'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'hog_pen_id.exists' => 'The selected hog pen does not exist.',
            'device_id.required' => 'The device ID is required.',
            'device_id.exists' => 'The selected device does not exist.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be active, inactive, or maintenance.',
            'last_refill.date' => 'The last refill must be a valid date.',
        ];
    }

}
