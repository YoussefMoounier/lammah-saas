<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncWooCommerceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'queued' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array'],
            'resources.*' => [Rule::in(['categories', 'products', 'customers', 'orders'])],
        ];
    }
}
