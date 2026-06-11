<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_stores', function (Blueprint $table): void {
            $table->string('connector_token_hash', 64)->nullable()->after('webhook_secret_encrypted');
            $table->string('connector_status', 32)->default('not_configured')->after('connector_token_hash');
            $table->timestamp('connector_last_seen_at')->nullable()->after('connector_status');
            $table->string('connector_version', 32)->nullable()->after('connector_last_seen_at');
            $table->text('connector_last_error')->nullable()->after('connector_version');

            $table->index(['merchant_id', 'connector_status']);
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_stores', function (Blueprint $table): void {
            $table->dropIndex(['merchant_id', 'connector_status']);
            $table->dropColumn([
                'connector_token_hash',
                'connector_status',
                'connector_last_seen_at',
                'connector_version',
                'connector_last_error',
            ]);
        });
    }
};
