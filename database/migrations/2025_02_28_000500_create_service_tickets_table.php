<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('outlet_id')->index();
            $table->uuid('booking_id')->nullable()->index();
            $table->uuid('staff_id')->index();
            $table->string('status')->default('open');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('dp_amount', 14, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->decimal('staff_commission', 14, 2)->nullable();
            $table->timestamps();

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

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
