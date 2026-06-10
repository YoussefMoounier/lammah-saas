<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->decimal('cost_amount', 14, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source', 64)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'effective_from', 'effective_to']);
            $table->index(['store_id', 'woo_product_id']);
            $table->index('product_id');
        });

        Schema::create('payment_gateway_fee_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->string('gateway_code', 80);
            $table->string('gateway_label')->nullable();
            $table->decimal('percent_fee', 7, 4)->default(0);
            $table->decimal('fixed_fee', 14, 4)->default(0);
            $table->char('currency', 3)->default('SAR');
            $table->decimal('min_order_amount', 14, 4)->nullable();
            $table->decimal('max_order_amount', 14, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['merchant_id', 'gateway_code', 'is_active']);
            $table->index(['store_id', 'gateway_code']);
        });

        Schema::create('order_profit_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('woo_orders')->cascadeOnDelete();
            $table->decimal('gross_revenue', 14, 4)->default(0);
            $table->decimal('product_cost_total', 14, 4)->default(0);
            $table->decimal('gateway_fee_total', 14, 4)->default(0);
            $table->decimal('shipping_cost_total', 14, 4)->default(0);
            $table->decimal('refunds_total', 14, 4)->default(0);
            $table->decimal('tax_total', 14, 4)->default(0);
            $table->decimal('net_profit', 14, 4)->default(0);
            $table->decimal('margin_percent', 8, 4)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->timestamp('calculated_at');
            $table->jsonb('calculation_payload')->default('{}');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['merchant_id', 'calculated_at']);
            $table->index(['store_id', 'calculated_at']);
        });

        Schema::create('fixed_monthly_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['servers', 'ads', 'staff', 'tools', 'suppliers', 'other'])->default('other');
            $table->decimal('amount', 14, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->index(['merchant_id', 'is_active', 'starts_on']);
        });

        Schema::create('runway_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('net_profit', 14, 4)->default(0);
            $table->decimal('fixed_cost_total', 14, 4)->default(0);
            $table->decimal('cash_balance', 14, 4)->nullable();
            $table->decimal('burn_rate', 14, 4)->default(0);
            $table->decimal('runway_months', 8, 2)->nullable();
            $table->char('currency', 3)->default('SAR');
            $table->timestamp('calculated_at');
            $table->jsonb('payload')->default('{}');
            $table->timestamps();

            $table->unique(['merchant_id', 'period_start', 'period_end']);
            $table->index(['merchant_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runway_snapshots');
        Schema::dropIfExists('fixed_monthly_costs');
        Schema::dropIfExists('order_profit_snapshots');
        Schema::dropIfExists('payment_gateway_fee_rules');
        Schema::dropIfExists('product_costs');
    }
};
