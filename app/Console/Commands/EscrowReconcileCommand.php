<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class EscrowReconcileCommand extends Command
{
    protected $signature = 'escrow:reconcile
                            {--tenant= : Tenant ID to report (default: all)}
                            {--export= : Export to CSV path}';

    protected $description = 'Report owner_escrow balance per tenant from ledger (reconciliation).';

    public function handle(LedgerService $ledgerService): int
    {
        $tenantId = $this->option('tenant');
        $exportPath = $this->option('export');

        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::query()->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenant(s) found.');
            return self::FAILURE;
        }

        $rows = [];
        $total = 0.0;

        foreach ($tenants as $tenant) {
            $balance = $ledgerService->getOwnerEscrowBalance($tenant->id);
            $total += $balance;
            $rows[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan_type' => $tenant->plan_type,
                'plan_status' => $tenant->plan_status,
                'balance' => round($balance, 2),
            ];
        }

        $this->table(
            ['Tenant ID', 'Name', 'Plan', 'Plan Status', 'Escrow Balance (Rp)'],
            array_map(fn ($r) => [$r['id'], $r['name'], $r['plan_type'], $r['plan_status'], number_format($r['balance'], 0, ',', '.')], $rows)
        );
        $this->line('Total (owner_escrow): Rp '.number_format($total, 0, ',', '.'));

        if ($exportPath) {
            $fp = fopen($exportPath, 'w');
            fputcsv($fp, ['tenant_id', 'name', 'plan_type', 'plan_status', 'owner_escrow_balance']);
            foreach ($rows as $r) {
                fputcsv($fp, [$r['id'], $r['name'], $r['plan_type'], $r['plan_status'], $r['balance']]);
            }
            fclose($fp);
            $this->info("Exported to {$exportPath}");
        }

        return self::SUCCESS;
    }
}
