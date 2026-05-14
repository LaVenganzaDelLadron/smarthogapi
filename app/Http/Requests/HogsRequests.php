<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HogsRequests extends FormRequest
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
            'ear_tag_id' => ['required', 'string', 'max:50', 'unique:hogs,ear_tag_id'],
            'breed' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other'],
            'current_age' => ['required', 'integer', 'min:0', 'max:500'],
            'weight_current' => ['required', 'numeric', 'min:0'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules = [
                'hog_pen_id' => ['sometimes', 'required', 'exists:hog_pens,id'],
                'ear_tag_id' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('hogs', 'ear_tag_id')->ignore($this->route('hog')),
                ],
                'breed' => ['sometimes', 'required', 'string', 'max:100'],
                'gender' => ['sometimes', 'required', 'in:male,female,other'],
                'current_age' => ['sometimes', 'required', 'integer', 'min:0', 'max:500'],
                'weight_current' => ['sometimes', 'required', 'numeric', 'min:0'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hog_pen_id.required' => 'The hog pen ID is required.',
            'hog_pen_id.exists' => 'The selected hog pen ID does not exist.',
            'ear_tag_id.required' => 'The ear tag ID is required.',
            'ear_tag_id.string' => 'The ear tag ID must be a string.',
            'ear_tag_id.max' => 'The ear tag ID may not be greater than 50 characters.',
            'ear_tag_id.unique' => 'The ear tag ID must be unique.',
        ];
    }
}
