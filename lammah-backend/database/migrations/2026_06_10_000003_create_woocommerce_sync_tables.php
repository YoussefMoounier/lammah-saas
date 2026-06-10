<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_customers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_customer_id')->nullable();
            $table->text('email_encrypted')->nullable();
            $table->string('email_hash', 64)->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_hash', 64)->nullable();
            $table->text('ip_address_encrypted')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('first_name_encrypted')->nullable();
            $table->text('last_name_encrypted')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('woo_created_at')->nullable();
            $table->timestamp('woo_updated_at')->nullable();
            $table->decimal('total_spent', 14, 4)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->jsonb('raw_payload')->default('{}');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'woo_customer_id']);
            $table->index(['store_id', 'email_hash']);
            $table->index(['store_id', 'phone_hash']);
            $table->index(['store_id', 'ip_hash']);
        });

        Schema::create('woo_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_category_id');
            $table->unsignedBigInteger('parent_woo_category_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('display')->nullable();
            $table->jsonb('raw_payload')->default('{}');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'woo_category_id']);
            $table->index(['store_id', 'slug']);
        });

        Schema::create('woo_products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_product_id');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('type', 32)->default('simple');
            $table->string('status', 32)->default('publish');
            $table->string('catalog_visibility', 32)->nullable();
            $table->decimal('regular_price', 14, 4)->nullable();
            $table->decimal('sale_price', 14, 4)->nullable();
            $table->decimal('current_price', 14, 4)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->string('stock_status', 32)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->boolean('manages_stock')->default(false);
            $table->boolean('is_virtual')->default(true);
            $table->boolean('is_downloadable')->default(false);
            $table->jsonb('attributes')->default('[]');
            $table->jsonb('raw_payload')->default('{}');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'woo_product_id']);
            $table->unique(['store_id', 'sku']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('woo_product_category', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->constrained('woo_products')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('woo_categories')->cascadeOnDelete();

            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('woo_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('woo_customers')->nullOnDelete();
            $table->unsignedBigInteger('woo_order_id');
            $table->string('woo_order_key')->nullable();
            $table->string('status', 32);
            $table->char('currency', 3)->default('SAR');
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount_total', 14, 4)->default(0);
            $table->decimal('shipping_total', 14, 4)->default(0);
            $table->decimal('tax_total', 14, 4)->default(0);
            $table->decimal('fee_total', 14, 4)->default(0);
            $table->decimal('refund_total', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_method_title')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('customer_email_encrypted')->nullable();
            $table->string('customer_email_hash', 64)->nullable();
            $table->text('customer_phone_encrypted')->nullable();
            $table->string('customer_phone_hash', 64)->nullable();
            $table->text('customer_ip_encrypted')->nullable();
            $table->string('customer_ip_hash', 64)->nullable();
            $table->char('billing_country', 2)->nullable();
            $table->timestamp('woo_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('billing_snapshot')->default('{}');
            $table->jsonb('shipping_snapshot')->default('{}');
            $table->jsonb('raw_payload')->default('{}');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'woo_order_id']);
            $table->index(['store_id', 'status', 'woo_created_at']);
            $table->index(['store_id', 'customer_email_hash']);
            $table->index(['store_id', 'customer_ip_hash']);
        });

        Schema::create('woo_order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('woo_orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->unsignedBigInteger('woo_order_item_id');
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->decimal('tax_total', 14, 4)->default(0);
            $table->char('currency', 3)->default('SAR');
            $table->jsonb('metadata')->default('{}');
            $table->jsonb('raw_payload')->default('{}');
            $table->timestamps();

            $table->unique(['order_id', 'woo_order_item_id']);
            $table->index(['store_id', 'woo_product_id']);
        });

        Schema::create('woo_webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->string('event_id')->nullable();
            $table->string('topic');
            $table->string('resource')->nullable();
            $table->string('woo_resource_id')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('signature_hash', 64)->nullable();
            $table->jsonb('headers')->default('{}');
            $table->jsonb('payload')->default('{}');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored'])->default('pending');
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'event_id']);
            $table->index(['store_id', 'topic', 'status']);
            $table->index(['received_at', 'status']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->string('sync_type', 64);
            $table->enum('status', ['queued', 'running', 'succeeded', 'failed', 'partial'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('cursor')->nullable();
            $table->jsonb('totals')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sync_type', 'status']);
            $table->index(['started_at', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('woo_webhook_events');
        Schema::dropIfExists('woo_order_items');
        Schema::dropIfExists('woo_orders');
        Schema::dropIfExists('woo_product_category');
        Schema::dropIfExists('woo_products');
        Schema::dropIfExists('woo_categories');
        Schema::dropIfExists('woo_customers');
    }
};
