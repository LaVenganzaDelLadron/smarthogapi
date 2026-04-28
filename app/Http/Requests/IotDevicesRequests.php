<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IotDevicesRequests extends FormRequest
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
            'type' => ['required', 'string', 'max:50'],
            'api_provider' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active, inactive'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'type' => ['sometimes', 'required', 'string', 'max:50'],
                'api_provider' => ['sometimes', 'required', 'string', 'max:50'],
                'status' => ['sometimes', 'required', 'string', 'in:active, inactive'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'type.required' => 'The type is required.',
        ];
    }

}
