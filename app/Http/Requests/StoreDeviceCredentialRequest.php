<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceCredentialRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'iot_device_id' => ['sometimes', 'nullable', 'exists:iot_devices,id'],
            'abilities' => ['sometimes', 'nullable', 'array'],
            'abilities.*' => ['string', 'in:*,commands:poll,commands:complete,logs:write'],
        ];
    }
}
