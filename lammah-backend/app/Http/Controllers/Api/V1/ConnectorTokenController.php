<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WooCommerceStoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConnectorTokenController extends Controller
{
    use AuthorizesMerchantAccess;

    public function __invoke(Request $request, string $merchant, string $store): JsonResponse
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'stores.manage');
        $token = 'lmh_' . Str::random(64);

        $storeModel->forceFill([
            'connector_token_hash' => hash('sha256', $token),
            'connector_status' => 'token_issued',
            'connector_last_error' => null,
            'metadata' => array_merge($storeModel->metadata ?? [], [
                'connector_token_rotated_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return response()->json([
            'data' => new WooCommerceStoreResource($storeModel->refresh()),
            'connector_token' => $token,
            'copy_once' => true,
        ]);
    }
}
