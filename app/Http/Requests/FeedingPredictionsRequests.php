<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedingPredictionsRequests extends FormRequest
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
            'hog_pen_id' => ['required', 'integer', 'exists:hog_pens,id'],
            'ml_model_id' => ['required', 'integer', 'exists:ml_models,id'],
            'predicted_feed_amount' => ['required', 'numeric', 'min:0'],
            'confidence_score' => ['required', 'numeric', 'between:0,100'],
            'model_used' => ['nullable', 'string', 'max:100'],
            'confidence_level' => ['nullable', 'string', 'max:50'],
            'confidence_reason' => ['nullable', 'string', 'max:1000'],
            'feed_recommendation' => ['nullable', 'array'],
            'feed_totals' => ['nullable', 'array'],
            'weight_trend' => ['nullable', 'array'],
            'pen_status' => ['nullable', 'array'],
            'warnings' => ['nullable', 'array'],
            'alerts' => ['nullable', 'array'],
            'suggestions' => ['nullable', 'array'],
            'fastapi_response' => ['nullable', 'array'],
            'predicted_at' => ['nullable', 'date'],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return collect($rules)
                ->map(fn (array $rule) => array_merge(['sometimes'], $rule))
                ->all();
        }

        return $rules;
    }
}
