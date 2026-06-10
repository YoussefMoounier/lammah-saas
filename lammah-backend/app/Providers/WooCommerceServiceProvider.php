<?php

namespace App\Providers;

use App\Services\WooCommerce\Contracts\WooCommerceClient;
use App\Services\WooCommerce\WooCommerceRestClient;
use Illuminate\Support\ServiceProvider;

class WooCommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WooCommerceClient::class, WooCommerceRestClient::class);
    }
}
