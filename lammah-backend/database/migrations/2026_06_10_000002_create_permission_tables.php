<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('group')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('merchant_id')->nullable()->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['merchant_id', 'name', 'guard_name']);
            $table->index(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUlid('merchant_id')->nullable()->constrained('merchants')->cascadeOnDelete();
            $table->string('model_type');
            $table->ulid('model_id');

            $table->unique(['permission_id', 'merchant_id', 'model_id', 'model_type'], 'model_permissions_unique');
            $table->index(['model_id', 'model_type'], 'model_permissions_model_index');
            $table->index('merchant_id');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('merchant_id')->nullable()->constrained('merchants')->cascadeOnDelete();
            $table->string('model_type');
            $table->ulid('model_id');

            $table->unique(['role_id', 'merchant_id', 'model_id', 'model_type'], 'model_roles_unique');
            $table->index(['model_id', 'model_type'], 'model_roles_model_index');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
