<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HogHealthPredictionsRequests extends FormRequest
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
            'hog_id' => ['required', 'exists:hogs,id'],
            'ml_model_id' => ['required', 'exists:ml_models,id'],
            'predicted_status' => ['required', 'string'],
            'risk_score' => ['required', 'numeric', 'between:0,1'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_id' => ['sometimes', 'required', 'exists:hogs,id'],
                'ml_model_id' => ['sometimes', 'required', 'exists:ml_models,id'],
                'predicted_status' => ['sometimes', 'required', 'string'],
                'risk_score' => ['sometimes', 'required', 'numeric', 'between:0,1'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_id.required' => 'The hog ID is required.',
            'ml_model_id.required' => 'The ML model ID is required.',
            'risk_score.between' => 'The risk score must be between 0 and 1.',
        ];
    }

}
