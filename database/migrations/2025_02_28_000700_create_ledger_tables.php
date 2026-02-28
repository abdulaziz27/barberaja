<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('reference_id');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference_id']);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('ledger_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entry_id')->index();
            $table->string('account')->index();
            $table->string('direction'); // debit|credit
            $table->decimal('amount', 14, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('entry_id')
                ->references('id')
                ->on('ledger_entries')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER IF NOT EXISTS prevent_ledger_entries_update BEFORE UPDATE ON ledger_entries BEGIN SELECT RAISE(ABORT, 'Ledger is immutable'); END;");
            DB::statement("CREATE TRIGGER IF NOT EXISTS prevent_ledger_entries_delete BEFORE DELETE ON ledger_entries BEGIN SELECT RAISE(ABORT, 'Ledger is immutable'); END;");
            DB::statement("CREATE TRIGGER IF NOT EXISTS prevent_ledger_lines_update BEFORE UPDATE ON ledger_lines BEGIN SELECT RAISE(ABORT, 'Ledger is immutable'); END;");
            DB::statement("CREATE TRIGGER IF NOT EXISTS prevent_ledger_lines_delete BEFORE DELETE ON ledger_lines BEGIN SELECT RAISE(ABORT, 'Ledger is immutable'); END;");
        }

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_ledger_mutation()
RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'Ledger is immutable';
END;
$$ LANGUAGE plpgsql;
SQL);

            DB::statement("DROP TRIGGER IF EXISTS prevent_ledger_entries_mutation ON ledger_entries;");
            DB::statement("CREATE TRIGGER prevent_ledger_entries_mutation BEFORE UPDATE OR DELETE ON ledger_entries FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();");
            DB::statement("DROP TRIGGER IF EXISTS prevent_ledger_lines_mutation ON ledger_lines;");
            DB::statement("CREATE TRIGGER prevent_ledger_lines_mutation BEFORE UPDATE OR DELETE ON ledger_lines FOR EACH ROW EXECUTE FUNCTION prevent_ledger_mutation();");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_entries_update;');
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_entries_delete;');
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_lines_update;');
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_lines_delete;');
        }

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_entries_mutation ON ledger_entries;');
            DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_lines_mutation ON ledger_lines;');
            DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_mutation();');
        }

        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('ledger_entries');
    }
};

