<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FarmsRequests extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'location' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:50'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'user_id' => ['sometimes', 'required', 'exists:users,id'],
                'location' => ['sometimes', 'required', 'string', 'max:255'],
                'timezone' => ['sometimes', 'required', 'string', 'max:50'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The selected user ID does not exist.',
            'location.required' => 'The location field is required.',
            'location.string' => 'The location must be a string.',
            'location.max' => 'The location may not be greater than 255 characters.',
            'timezone.required' => 'The timezone field is required.',
            'timezone.string' => 'The timezone must be a string.',
            'timezone.max' => 'The timezone may not be greater than 50 characters.',
        ];
    }
}
