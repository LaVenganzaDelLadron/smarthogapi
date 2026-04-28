<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SensorReadingsRequests extends FormRequest
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
            'sensor_id' => ['required', 'exists:sensors,id'],
            'value' => ['required', 'numeric'],
            'unit' => ['required', 'string', 'max:10'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'sensor_id' => ['sometimes', 'required', 'exists:sensors,id'],
                'value' => ['sometimes', 'required', 'numeric'],
                'unit' => ['sometimes', 'required', 'string', 'max:10'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'sensor_id.required' => 'The sensor ID is required.',
            'sensor_id.exists' => 'The selected sensor does not exist.',
            'value.required' => 'The value is required.',
            'value.numeric' => 'The value must be numeric.',
            'unit.required' => 'The unit is required.',
        ];
    }

}
