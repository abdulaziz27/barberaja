<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_type')->default('free')->after('subscription_status');
            $table->string('plan_status')->default('active')->after('plan_type');
            $table->date('plan_started_at')->nullable()->after('plan_status');
            $table->date('plan_expires_at')->nullable()->after('plan_started_at');
            $table->decimal('custom_fee_per_transaction', 14, 2)->nullable()->after('plan_expires_at');
            $table->decimal('custom_subscription_price', 14, 2)->nullable()->after('custom_fee_per_transaction');
            $table->timestamp('subscription_overdue_at')->nullable()->after('custom_subscription_price');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'plan_type',
                'plan_status',
                'plan_started_at',
                'plan_expires_at',
                'custom_fee_per_transaction',
                'custom_subscription_price',
                'subscription_overdue_at',
            ]);
        });
    }
};
