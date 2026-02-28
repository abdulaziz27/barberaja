<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bundle_id')->index();
            $table->uuid('product_id')->index();
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();

            $table->foreign('bundle_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Satu bundle tidak boleh memiliki item produk yang sama lebih dari sekali
            $table->unique(['bundle_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
