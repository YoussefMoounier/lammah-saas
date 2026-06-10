<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_metric_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->string('metric_type', 80);
            $table->enum('engine', ['laravel_native', 'fastapi', 'manual'])->default('laravel_native');
            $table->enum('status', ['queued', 'running', 'succeeded', 'failed'])->default('queued');
            $table->timestamp('input_window_start')->nullable();
            $table->timestamp('input_window_end')->nullable();
            $table->jsonb('parameters')->default('{}');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('result_summary')->default('{}');
            $table->timestamps();

            $table->index(['merchant_id', 'metric_type', 'status']);
            $table->index(['store_id', 'metric_type']);
        });

        Schema::create('sales_forecasts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('metric_run_id')->nullable()->constrained('ai_metric_runs')->nullOnDelete();
            $table->date('forecast_month');
            $table->unsignedSmallInteger('training_months')->default(6);
            $table->decimal('gross_revenue_forecast', 14, 4)->default(0);
            $table->decimal('net_profit_forecast', 14, 4)->nullable();
            $table->unsignedInteger('order_count_forecast')->nullable();
            $table->decimal('confidence_low', 14, 4)->nullable();
            $table->decimal('confidence_high', 14, 4)->nullable();
            $table->jsonb('model_coefficients')->default('{}');
            $table->date('source_window_start')->nullable();
            $table->date('source_window_end')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['store_id', 'forecast_month']);
            $table->index(['merchant_id', 'forecast_month']);
        });

        Schema::create('fraud_risk_signals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('woo_customers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('woo_orders')->nullOnDelete();
            $table->foreignUlid('metric_run_id')->nullable()->constrained('ai_metric_runs')->nullOnDelete();
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->string('signal_type', 80);
            $table->jsonb('evidence')->default('{}');
            $table->enum('status', ['open', 'reviewing', 'dismissed', 'confirmed'])->default('open');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'severity', 'status']);
            $table->index(['store_id', 'signal_type']);
            $table->index(['customer_id', 'risk_score']);
        });

        Schema::create('rfm_customer_scores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('woocommerce_stores')->nullOnDelete();
            $table->foreignUlid('customer_id')->constrained('woo_customers')->cascadeOnDelete();
            $table->foreignUlid('metric_run_id')->nullable()->constrained('ai_metric_runs')->nullOnDelete();
            $table->unsignedInteger('recency_days')->default(0);
            $table->unsignedInteger('frequency_orders')->default(0);
            $table->decimal('monetary_value', 14, 4)->default(0);
            $table->unsignedSmallInteger('r_score')->default(1);
            $table->unsignedSmallInteger('f_score')->default(1);
            $table->unsignedSmallInteger('m_score')->default(1);
            $table->string('segment', 40)->default('new');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->decimal('churn_probability', 5, 2)->nullable();
            $table->timestamp('calculated_at');
            $table->jsonb('signals')->default('{}');
            $table->timestamps();

            $table->unique(['customer_id', 'metric_run_id']);
            $table->index(['merchant_id', 'segment', 'calculated_at']);
            $table->index(['store_id', 'churn_probability']);
        });

        Schema::create('surge_pricing_recommendations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('metric_run_id')->nullable()->constrained('ai_metric_runs')->nullOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->timestamp('traffic_window_start');
            $table->timestamp('traffic_window_end');
            $table->unsignedInteger('baseline_checkout_count')->default(0);
            $table->unsignedInteger('current_checkout_count')->default(0);
            $table->decimal('spike_percent', 8, 2)->default(0);
            $table->decimal('recommended_increase_percent', 5, 2)->default(0);
            $table->enum('status', ['suggested', 'accepted', 'rejected', 'applied', 'expired'])->default('suggested');
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUlid('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamps();

            $table->index(['store_id', 'status', 'expires_at']);
            $table->index(['product_id', 'traffic_window_start']);
        });

        Schema::create('upsell_recommendations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('woocommerce_stores')->cascadeOnDelete();
            $table->foreignUlid('metric_run_id')->nullable()->constrained('ai_metric_runs')->nullOnDelete();
            $table->foreignUlid('trigger_product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->foreignUlid('suggested_product_id')->nullable()->constrained('woo_products')->nullOnDelete();
            $table->unsignedBigInteger('trigger_woo_product_id')->nullable();
            $table->unsignedBigInteger('suggested_woo_product_id')->nullable();
            $table->decimal('support_score', 8, 4)->default(0);
            $table->decimal('confidence_score', 8, 4)->default(0);
            $table->decimal('lift_score', 8, 4)->nullable();
            $table->string('recommended_offer_text')->nullable();
            $table->enum('status', ['active', 'dismissed', 'applied', 'expired'])->default('active');
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamps();

            $table->unique(['store_id', 'trigger_product_id', 'suggested_product_id'], 'upsell_pair_unique');
            $table->index(['merchant_id', 'status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_recommendations');
        Schema::dropIfExists('surge_pricing_recommendations');
        Schema::dropIfExists('rfm_customer_scores');
        Schema::dropIfExists('fraud_risk_signals');
        Schema::dropIfExists('sales_forecasts');
        Schema::dropIfExists('ai_metric_runs');
    }
};
