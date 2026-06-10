<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_url')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 12)->default('en');
            $table->enum('status', ['active', 'invited', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('merchants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->text('billing_email_encrypted')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('default_currency', 3)->default('SAR');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->enum('status', ['trial', 'active', 'paused', 'suspended'])->default('trial');
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status']);
        });

        Schema::create('merchant_user', function (Blueprint $table): void {
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['invited', 'active', 'disabled'])->default('invited');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['merchant_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('woocommerce_stores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url', 2048);
            $table->string('base_url_hash', 64);
            $table->text('consumer_key_encrypted');
            $table->text('consumer_secret_encrypted');
            $table->text('webhook_secret_encrypted')->nullable();
            $table->string('api_version', 16)->default('wc/v3');
            $table->char('currency', 3)->default('SAR');
            $table->string('timezone', 64)->default('UTC');
            $table->enum('status', ['active', 'disabled', 'error'])->default('active');
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('last_failed_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('sync_settings')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['merchant_id', 'base_url_hash']);
            $table->index(['merchant_id', 'status']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('woocommerce_stores');
        Schema::dropIfExists('merchant_user');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('users');
    }
};
