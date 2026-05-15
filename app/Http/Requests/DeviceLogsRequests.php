<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceLogsRequests extends FormRequest
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
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'device_id' => ['required', 'integer', 'exists:iot_devices,id'],
            'action' => ['required', 'string', 'max:255'],
            'response' => ['required', 'string', 'max:1000'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return collect($rules)
                ->map(fn (array $rule) => array_merge(['sometimes'], $rule))
                ->all();
        }

        return $rules;
    }
}
