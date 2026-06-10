<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LammahPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $permissions = collect([
            ['name' => 'stores.view', 'group' => 'stores', 'description' => 'View connected WooCommerce stores.'],
            ['name' => 'stores.manage', 'group' => 'stores', 'description' => 'Connect, edit, and disable WooCommerce stores.'],
            ['name' => 'orders.view', 'group' => 'orders', 'description' => 'View synced orders.'],
            ['name' => 'orders.manage', 'group' => 'orders', 'description' => 'Update order operational status and delivery state.'],
            ['name' => 'customers.view', 'group' => 'customers', 'description' => 'View customer profiles and subscription status.'],
            ['name' => 'customers.export', 'group' => 'customers', 'description' => 'Export customer data.'],
            ['name' => 'revenue.view', 'group' => 'finance', 'description' => 'View gross revenue, net profit, and runway.'],
            ['name' => 'analytics.view', 'group' => 'analytics', 'description' => 'View AI forecasts, fraud radar, RFM, and upsell recommendations.'],
            ['name' => 'pricing.manage', 'group' => 'pricing', 'description' => 'Run bulk price edits and surge pricing changes.'],
            ['name' => 'delivery_templates.manage', 'group' => 'operations', 'description' => 'Manage canned delivery templates.'],
            ['name' => 'shifts.manage', 'group' => 'staff', 'description' => 'Create and close staff shifts.'],
            ['name' => 'staff.manage', 'group' => 'staff', 'description' => 'Invite staff and manage permissions.'],
            ['name' => 'settings.manage', 'group' => 'settings', 'description' => 'Manage merchant-wide settings.'],
        ])->map(fn (array $permission): array => [
            'id' => (string) Str::ulid(),
            'name' => $permission['name'],
            'guard_name' => 'web',
            'group' => $permission['group'],
            'description' => $permission['description'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permissions')->upsert(
            $permissions->all(),
            ['name', 'guard_name'],
            ['group', 'description', 'updated_at']
        );

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissions->pluck('name'))
            ->pluck('id', 'name');

        $roles = collect([
            [
                'name' => 'owner',
                'description' => 'Full merchant owner access.',
                'permissions' => $permissionIds->keys()->all(),
            ],
            [
                'name' => 'admin',
                'description' => 'Operational admin access without staff ownership controls.',
                'permissions' => [
                    'stores.view',
                    'orders.view',
                    'orders.manage',
                    'customers.view',
                    'revenue.view',
                    'analytics.view',
                    'pricing.manage',
                    'delivery_templates.manage',
                    'shifts.manage',
                ],
            ],
            [
                'name' => 'support',
                'description' => 'Support agent access without revenue, exports, or settings.',
                'permissions' => [
                    'orders.view',
                    'orders.manage',
                    'customers.view',
                    'delivery_templates.manage',
                    'shifts.manage',
                ],
            ],
        ]);

        foreach ($roles as $role) {
            $roleId = DB::table('roles')
                ->whereNull('merchant_id')
                ->where('name', $role['name'])
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId === null) {
                $roleId = (string) Str::ulid();

                DB::table('roles')->insert([
                    'id' => $roleId,
                    'merchant_id' => null,
                    'name' => $role['name'],
                    'guard_name' => 'web',
                    'description' => $role['description'],
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('roles')
                    ->where('id', $roleId)
                    ->update([
                        'description' => $role['description'],
                        'is_system' => true,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($role['permissions'] as $permissionName) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionName],
                ]);
            }
        }
    }
}
