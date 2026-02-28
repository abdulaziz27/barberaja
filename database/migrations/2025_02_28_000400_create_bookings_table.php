<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('outlet_id')->index();
            $table->uuid('staff_id')->index();
            $table->uuid('product_id')->nullable()->index();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedInteger('duration_minutes');
            $table->string('source')->default('kasir'); // online, qr, kasir
            $table->string('status')->default('confirmed');
            $table->decimal('dp_amount', 14, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->timestamps();

            $table->index(['staff_id', 'start_time']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('outlet_id')
                ->references('id')
                ->on('outlets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
