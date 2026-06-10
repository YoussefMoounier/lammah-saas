<?php

namespace App\Services\WooCommerce\DTO;

final readonly class WooCommercePage
{
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public ?int $total,
        public ?int $totalPages,
    ) {
    }

    public function hasNextPage(): bool
    {
        return $this->totalPages !== null && $this->page < $this->totalPages;
    }
}
