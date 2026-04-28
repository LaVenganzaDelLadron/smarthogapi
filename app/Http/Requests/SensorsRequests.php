<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SensorsRequests extends FormRequest
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
            'sensor_type' => ['required', 'string', 'max:50'],
            'device_id' => ['required', 'exists:iot_devices,id'],
            'status' => ['required', 'string', 'in:active, inactive'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'sensor_type' => ['sometimes', 'required', 'string', 'max:50'],
                'device_id' => ['sometimes', 'required', 'exists:iot_devices,id'],
                'status' => ['sometimes', 'required', 'string', 'in:active, inactive'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'hog_pen_id.exists' => 'The selected hog pen does not exist.',
            'sensor_type.required' => 'The sensor type is required.',
            'device_id.required' => 'The device ID is required.',
            'status.required' => 'The status is required.',
        ];
    }

}
