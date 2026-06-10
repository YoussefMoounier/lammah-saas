<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPriceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['flat', 'percent', 'set'])],
            'value' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'target_type' => ['required', Rule::in(['all', 'category', 'product', 'query'])],
            'target_filters' => ['nullable', 'array'],
            'target_filters.category_ids' => ['nullable', 'array'],
            'target_filters.category_ids.*' => ['string'],
            'target_filters.woo_category_ids' => ['nullable', 'array'],
            'target_filters.woo_category_ids.*' => ['integer'],
            'target_filters.product_ids' => ['nullable', 'array'],
            'target_filters.product_ids.*' => ['string'],
            'target_filters.woo_product_ids' => ['nullable', 'array'],
            'target_filters.woo_product_ids.*' => ['integer'],
            'target_filters.search' => ['nullable', 'string', 'max:120'],
            'target_filters.status' => ['nullable', 'string', 'max:32'],
            'scheduled_at' => ['nullable', 'date'],
            'execute_now' => ['nullable', 'boolean'],
        ];
    }
}
