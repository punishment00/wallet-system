<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Wallet;

class RebateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $wallet_id;
    protected $amount;

    /**
     * Create a new job instance.
     */
    public function __construct($wallet_id, $amount)
    {
        $this->wallet_id = $wallet_id;
        $this->amount = $amount;
    }

    public function getWalletId()
    {
        return $this->wallet_id;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            Log::info("RebateJob started for wallet ID: {$this->wallet_id}, amount: {$this->amount}");
            $wallet = Wallet::where('id', $this->wallet_id)->lockForUpdate()->first();

            if ($wallet) {
                $rebate_amount = bcmul($this->amount, 0.01, 2);

                // update deposit amount with rebate amount
                $add_balance = bcadd($wallet->balance, $rebate_amount, 2);
                $wallet->balance = $add_balance;
                $wallet->save();

                // transaction record
                $wallet->transactions()->create(['type' => 'rebate', 'amount' => $rebate_amount]);
                Log::info("success");
            } else {
                Log::error("invalid wallet_id " . $this->wallet_id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());
        }
    }
}
