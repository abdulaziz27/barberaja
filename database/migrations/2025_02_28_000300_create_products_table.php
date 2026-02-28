<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('outlet_id')->nullable()->index();
            $table->string('name');
            $table->string('type')->default('service');
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('stock_qty')->nullable();
            $table->string('commission_type')->default('none');
            $table->decimal('commission_value', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('outlet_id')
                ->references('id')
                ->on('outlets')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
