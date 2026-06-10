<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseOneSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_tables_exist(): void
    {
        $tables = [
            'users',
            'merchants',
            'merchant_user',
            'woocommerce_stores',
            'personal_access_tokens',
            'permissions',
            'roles',
            'woo_customers',
            'woo_categories',
            'woo_products',
            'woo_orders',
            'woo_order_items',
            'subscription_entitlements',
            'delivery_credentials',
            'price_update_batches',
            'product_costs',
            'order_profit_snapshots',
            'ai_metric_runs',
            'sales_forecasts',
            'fraud_risk_signals',
            'rfm_customer_scores',
            'surge_pricing_recommendations',
            'upsell_recommendations',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} table is missing.");
        }
    }

    public function test_sensitive_customer_lookup_columns_are_split_between_encrypted_and_hash_values(): void
    {
        $this->assertTrue(Schema::hasColumns('woo_customers', [
            'email_encrypted',
            'email_hash',
            'phone_encrypted',
            'phone_hash',
            'ip_address_encrypted',
            'ip_hash',
        ]));

        $this->assertTrue(Schema::hasColumns('woo_orders', [
            'customer_email_encrypted',
            'customer_email_hash',
            'customer_phone_encrypted',
            'customer_phone_hash',
            'customer_ip_encrypted',
            'customer_ip_hash',
        ]));
    }

    public function test_store_order_and_product_woocommerce_ids_are_unique_per_store(): void
    {
        $userId = (string) Str::ulid();
        $merchantId = (string) Str::ulid();
        $storeId = (string) Str::ulid();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => 'hashed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('merchants')->insert([
            'id' => $merchantId,
            'owner_id' => $userId,
            'company_name' => 'Lammah Demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('woocommerce_stores')->insert([
            'id' => $storeId,
            'merchant_id' => $merchantId,
            'name' => 'Demo Store',
            'base_url' => 'https://store.example.test',
            'base_url_hash' => hash('sha256', 'https://store.example.test'),
            'consumer_key_encrypted' => 'encrypted-key',
            'consumer_secret_encrypted' => 'encrypted-secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('woo_products')->insert([
            'id' => (string) Str::ulid(),
            'store_id' => $storeId,
            'woo_product_id' => 100,
            'name' => '12 Month IPTV',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('woo_products')->insert([
            'id' => (string) Str::ulid(),
            'store_id' => $storeId,
            'woo_product_id' => 100,
            'name' => 'Duplicate 12 Month IPTV',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_staff_roles_are_assignable_inside_a_merchant_scope(): void
    {
        $merchantId = (string) Str::ulid();
        $ownerId = (string) Str::ulid();
        $agentId = (string) Str::ulid();
        $roleId = (string) Str::ulid();

        DB::table('users')->insert([
            [
                'id' => $ownerId,
                'name' => 'Owner',
                'email' => 'owner-rbac@example.test',
                'password' => 'hashed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $agentId,
                'name' => 'Agent',
                'email' => 'agent@example.test',
                'password' => 'hashed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('merchants')->insert([
            'id' => $merchantId,
            'owner_id' => $ownerId,
            'company_name' => 'RBAC Demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roles')->insert([
            'id' => $roleId,
            'merchant_id' => $merchantId,
            'name' => 'support',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'id' => (string) Str::ulid(),
            'role_id' => $roleId,
            'merchant_id' => $merchantId,
            'model_type' => 'App\\Models\\User',
            'model_id' => $agentId,
        ]);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $roleId,
            'merchant_id' => $merchantId,
            'model_id' => $agentId,
        ]);
    }
}
