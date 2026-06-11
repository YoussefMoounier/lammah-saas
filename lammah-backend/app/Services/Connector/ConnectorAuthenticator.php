<?php

namespace App\Services\Connector;

use App\Models\WooCommerceStore;
use Illuminate\Http\Request;

class ConnectorAuthenticator
{
    public function authenticate(Request $request, string $storeId): WooCommerceStore
    {
        $store = WooCommerceStore::query()
            ->where('status', 'active')
            ->findOrFail($storeId);

        $token = $request->bearerToken();

        abort_unless($token && $store->connector_token_hash, 401, 'Connector token is required.');
        abort_unless(hash_equals($store->connector_token_hash, hash('sha256', $token)), 401, 'Invalid connector token.');

        return $store;
    }
}
