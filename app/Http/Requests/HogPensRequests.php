<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HogPensRequests extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:0', 'max:10000'],
            'status' => ['required', 'integer', 'between:0,1'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'farm_id' => ['sometimes', 'required', 'exists:farms,id'],
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'capacity' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
                'status' => ['sometimes', 'required', 'integer', 'between:0,1'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'farm_id.required' => 'The farm ID is required.',
            'farm_id.exists' => 'The selected farm ID does not exist.',
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'capacity.required' => 'The capacity field is required.',
            'capacity.integer' => 'The capacity must be an integer.',
            'capacity.min' => 'The capacity must be at least 0.',
            'capacity.max' => 'The capacity may not be greater than 10000.',
            'status.required' => 'The status field is required.',
            'status.integer' => 'The status must be an integer.',
            'status.between' => 'The status must be 0 or 1.',
        ];
    }
}
