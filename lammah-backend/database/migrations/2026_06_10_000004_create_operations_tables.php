<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('woo_orders')->nullOnDelete();
            $table->foreignUlid('order_item_id')->nullable()->constrained('woo_order_items')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('woo_customers')->nullOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->enum('status', ['trial', 'active', 'expired', 'canceled', 'suspended'])->default('active');
            $table->string('plan_name')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->jsonb('source')->default('{}');
            $table->timestamps();

            $table->index(['merchant_id', 'status', 'expires_at']);
            $table->index(['store_id', 'customer_id']);
        });

        Schema::create('delivery_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('entitlement_id')->nullable()->constrained('subscription_entitlements')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('woo_orders')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('woo_customers')->nullOnDelete();
            $table->string('channel', 32)->default('iptv');
            $table->text('server_url_encrypted')->nullable();
            $table->text('portal_url_encrypted')->nullable();
            $table->text('username_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->text('mac_address_encrypted')->nullable();
            $table->text('device_notes_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->jsonb('delivery_channels')->default('[]');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['merchant_id', 'channel']);
            $table->index(['entitlement_id', 'expires_at']);
        });

        Schema::create('delivery_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 32)->default('whatsapp');
            $table->string('locale', 12)->default('en');
            $table->text('body');
            $table->jsonb('placeholders')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'name', 'channel', 'locale']);
            $table->index(['merchant_id', 'is_default']);
        });

        Schema::create('shifts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['scheduled', 'active', 'closed', 'canceled'])->default('scheduled');
            $table->decimal('opening_cash', 14, 4)->nullable();
            $table->decimal('closing_cash', 14, 4)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('shift_order_attributions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('woo_orders')->cascadeOnDelete();
            $table->foreignUlid('attributed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('gross_revenue', 14, 4)->default(0);
            $table->decimal('net_profit', 14, 4)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->timestamp('attributed_at');
            $table->timestamps();

            $table->unique(['shift_id', 'order_id']);
            $table->index(['attributed_user_id', 'attributed_at']);
        });

        Schema::create('price_update_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('mode', ['flat', 'percent', 'set']);
            $table->decimal('value', 14, 4);
            $table->char('currency', 3)->nullable();
            $table->enum('target_type', ['all', 'category', 'product', 'query']);
            $table->jsonb('target_filters')->default('{}');
            $table->enum('status', ['draft', 'queued', 'running', 'completed', 'failed', 'rolled_back'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['store_id', 'target_type']);
        });

        Schema::create('price_update_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('price_update_batches')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->decimal('old_regular_price', 14, 4)->nullable();
            $table->decimal('old_sale_price', 14, 4)->nullable();
            $table->decimal('new_regular_price', 14, 4)->nullable();
            $table->decimal('new_sale_price', 14, 4)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['pending', 'applied', 'failed', 'rolled_back'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->jsonb('rollback_payload')->default('{}');
            $table->timestamps();

            $table->unique(['batch_id', 'product_id']);
            $table->index(['batch_id', 'status']);
            $table->index('woo_product_id');
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableUlidMorphs('auditable');
            $table->string('event_type', 80);
            $table->string('action', 120);
            $table->text('ip_address_encrypted')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['merchant_id', 'event_type', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index('ip_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('price_update_items');
        Schema::dropIfExists('price_update_batches');
        Schema::dropIfExists('shift_order_attributions');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('delivery_templates');
        Schema::dropIfExists('delivery_credentials');
        Schema::dropIfExists('subscription_entitlements');
    }
};
