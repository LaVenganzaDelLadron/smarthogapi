<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSinricWebhookRequest extends FormRequest
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
            'deviceId' => ['required', 'string', 'max:255'],
            'action' => ['required', 'string', 'in:setPowerState'],
            'value' => ['required', 'array:state'],
            'value.state' => ['required', 'string', 'in:On,Off'],
        ];
    }
}
