<?php

namespace Seat\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Seat\Eveapi\Models\Contracts\ContractDetail;
use Seat\Billing\Models\BillingTax;
use Carbon\Carbon;

class AddHaulingTaxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Billing period: previous calendar month
        $start = Carbon::now()->startOfMonth()->subMonth();
        $end   = Carbon::now()->startOfMonth();

        $contracts = ContractDetail::where('type', 'courier')
            ->where('status', 'finished')
            ->whereBetween('date_completed', [$start, $end])
            ->whereNotNull('reward')
            ->where('reward', '>', 0)
            ->get();

        foreach ($contracts as $contract) {
            $tax_amount = $contract->reward * 0.05;

            if ($tax_amount <= 0) continue;

            $corporation_id = $contract->issuer_corporation_id;
            $character_id = $contract->acceptor_id;

            if (BillingTax::where('source_id', $contract->contract_id)->where('type', 'hauling')->exists()) {
                continue;
            }

            BillingTax::create([
                'corporation_id' => $corporation_id,
                'character_id'   => $character_id,
                'amount'         => $tax_amount,
                'type'           => 'hauling',
                'description'    => '5% tax on hauling contract reward (Contract #' . $contract->contract_id . ')',
                'source_id'      => $contract->contract_id,
            ]);
        }
    }
}
