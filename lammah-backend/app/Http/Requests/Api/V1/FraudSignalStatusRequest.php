<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FraudSignalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'reviewing', 'dismissed', 'confirmed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
