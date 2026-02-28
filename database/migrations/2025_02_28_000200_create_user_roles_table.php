<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('tenant_id');
            $table->uuid('outlet_id')->nullable();
            $table->string('role'); // owner, manager, staff, platform_admin
            $table->timestamps();

            $table->index('user_id');
            $table->index('tenant_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('outlet_id')
                ->references('id')
                ->on('outlets')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['user_id', 'tenant_id', 'outlet_id', 'role'], 'user_roles_unique_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};

