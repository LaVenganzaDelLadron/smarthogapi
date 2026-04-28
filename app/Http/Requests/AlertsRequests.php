<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlertsRequests extends FormRequest
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
            'farm_id' => ['required', 'exists:farms,id'],
            'hog_pen_id' => ['required', 'exists:hog_pens,id'],
            'type' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'string', 'in:active,resolved'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'farm_id' => ['sometimes', 'required', 'exists:farms,id'],
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'type' => ['sometimes', 'required', 'string', 'max:50'],
                'message' => ['sometimes', 'required', 'string', 'max:1000'],
                'severity' => ['sometimes', 'required', 'in:low,medium,high,critical'],
                'status' => ['sometimes', 'required', 'string', 'in:active,resolved'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'farm_id.required' => 'The farm ID is required.',
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'severity.in' => 'Severity must be low, medium, high or critical.',
        ];
    }

}
