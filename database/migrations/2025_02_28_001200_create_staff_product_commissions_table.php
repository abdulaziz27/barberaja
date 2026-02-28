<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-staff commission override for a specific product.
     * If a row exists for (staff_id, product_id), it overrides the product's default commission.
     */
    public function up(): void
    {
        Schema::create('staff_product_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('staff_id')->index();
            $table->uuid('product_id')->index();
            $table->string('commission_type'); // percentage | fixed | none
            $table->decimal('commission_value', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // One override per (staff, product) pair
            $table->unique(['staff_id', 'product_id'], 'staff_product_commission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_product_commissions');
    }
};
