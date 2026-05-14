<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceActionRequest extends FormRequest
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
            'action' => ['required', 'string', 'max:100', 'in:dispenseFeed,setPowerState,restartDevice,calibrateSensor'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'payload.feedType' => ['required_if:action,dispenseFeed', 'string', 'max:100'],
            'payload.durationSeconds' => ['required_if:action,dispenseFeed', 'integer', 'min:1', 'max:3600'],
            'payload.amount' => ['sometimes', 'numeric', 'min:0'],
            'payload.state' => ['required_if:action,setPowerState', 'string', 'in:on,off'],
        ];
    }
}
