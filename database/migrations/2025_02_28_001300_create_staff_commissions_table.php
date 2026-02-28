<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the commission earned by a staff member for a completed ticket.
     * Insert-only (no update/delete) — immutable record of earned commission.
     */
    public function up(): void
    {
        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('staff_id')->index();
            $table->uuid('ticket_id')->index();
            $table->decimal('amount', 14, 2);
            $table->json('breakdown')->nullable(); // per-item breakdown for transparency
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
                ->restrictOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('service_tickets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // One commission record per ticket (idempotency)
            $table->unique('ticket_id', 'staff_commissions_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commissions');
    }
};
