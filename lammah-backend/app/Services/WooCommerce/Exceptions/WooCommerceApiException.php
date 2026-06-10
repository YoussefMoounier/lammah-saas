<?php

namespace App\Services\WooCommerce\Exceptions;

use RuntimeException;

class WooCommerceApiException extends RuntimeException
{
    public function __construct(
        public readonly string $endpoint,
        public readonly ?int $statusCode,
        public readonly array|string|null $responseBody,
        string $message = 'WooCommerce API request failed.',
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }
}
