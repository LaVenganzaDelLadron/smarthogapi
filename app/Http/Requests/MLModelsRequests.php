<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MLModelsRequests extends FormRequest
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
            'model_name' => ['required', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:20'],
            'accuracy_score' => ['required', 'numeric', 'between:0,1'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'model_name' => ['sometimes', 'required', 'string', 'max:100'],
                'version' => ['sometimes', 'required', 'string', 'max:20'],
                'accuracy_score' => ['sometimes', 'required', 'numeric', 'between:0,1'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'model_name.required' => 'The model name is required.',
            'version.required' => 'The version is required.',
            'accuracy_score.required' => 'The accuracy score is required.',
            'accuracy_score.between' => 'The accuracy score must be between 0 and 1.',
        ];
    }

}
