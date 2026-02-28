<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->index();
            $table->uuid('product_id')->index();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('price', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('service_tickets')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
    }
};
