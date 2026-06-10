<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'training_months' => ['nullable', 'integer', 'min:2', 'max:24'],
            'queued' => ['nullable', 'boolean'],
        ];
    }
}
