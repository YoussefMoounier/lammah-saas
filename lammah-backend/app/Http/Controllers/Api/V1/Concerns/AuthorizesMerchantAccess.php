<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Merchant;
use App\Models\WooCommerceStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait AuthorizesMerchantAccess
{
    protected function authorizeMerchant(Request $request, string $merchantId, ?string $permission = null): Merchant
    {
        $user = $request->user();

        abort_unless($user, 401, 'Authentication required.');

        $merchant = Merchant::query()->findOrFail($merchantId);
        $isOwner = $merchant->owner_id === $user->getKey();
        $isMember = DB::table('merchant_user')
            ->where('merchant_id', $merchant->id)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->exists();

        abort_unless($isOwner || $isMember, 403, 'You do not have access to this merchant.');

        if ($permission !== null && ! $isOwner && ! $this->userHasMerchantPermission($user->getKey(), $merchant->id, $permission)) {
            throw new HttpException(403, "Missing permission [{$permission}].");
        }

        return $merchant;
    }

    protected function authorizeStore(Request $request, string $merchantId, string $storeId, ?string $permission = null): WooCommerceStore
    {
        $merchant = $this->authorizeMerchant($request, $merchantId, $permission);

        return WooCommerceStore::query()
            ->where('merchant_id', $merchant->id)
            ->findOrFail($storeId);
    }

    private function userHasMerchantPermission(string $userId, string $merchantId, string $permission): bool
    {
        $modelType = 'App\\Models\\User';

        $directPermission = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', $modelType)
            ->where(function ($query) use ($merchantId): void {
                $query->whereNull('model_has_permissions.merchant_id')
                    ->orWhere('model_has_permissions.merchant_id', $merchantId);
            })
            ->where('permissions.name', $permission)
            ->exists();

        if ($directPermission) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where(function ($query) use ($merchantId): void {
                $query->whereNull('model_has_roles.merchant_id')
                    ->orWhere('model_has_roles.merchant_id', $merchantId);
            })
            ->where(function ($query) use ($merchantId): void {
                $query->whereNull('roles.merchant_id')
                    ->orWhere('roles.merchant_id', $merchantId);
            })
            ->where('permissions.name', $permission)
            ->exists();
    }
}
