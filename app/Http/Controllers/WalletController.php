<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\RebateJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function deposit(Request $request)
    {
        $wallet_id = $request->input('wallet_id');
        $amount = $request->input('amount');

        // validation
        if (!is_numeric($amount) || $amount <= 0) {
            return response()->json(['error' => 'Deposit amount invalid'], 400);
        }

        DB::beginTransaction();
        try {
            // pessimistic lock
            // Log::info('testConcurrentDepositsWithRebate: id: ' . $wallet_id);
            $wallet = Wallet::where('id', $wallet_id)->lockForUpdate()->first();
            if (!$wallet) {
                return response()->json(['error' => 'Deposit Wallet not found'], 404);
                // throw new \Exception('Withdraw wallet not found');
            }

            // update deposit amount
            $add_balance = bcadd($wallet->balance, $amount, 2);
            $wallet->balance = $add_balance;
            // $wallet->balance += $amount;
            $wallet->save();

            // transaction record
            $wallet->transactions()->create(['type' => 'deposit', 'amount' => $amount]);

            // job
            // dispatch(new RebateJob($wallet->id, $amount));
            $rebate_job = new RebateJob($wallet->id, $amount);
            Bus::dispatchNow($rebate_job);

            // reetrieve latest wallet balance
            // $latest_wallet = Wallet::find($wallet_id);
            $latest_balance = $this->walletBalance($wallet_id, true);

            DB::commit();
            // Log::info($latest_balance);
            return response()->json([
                'message' => 'Deposit success',
                'balance' => $latest_balance
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'ERROR: ' . $e->getMessage()
            ], 400);
        }
    }

    public function withdraw(Request $request)
    {
        $wallet_id = $request->input('wallet_id');
        $amount = $request->input('amount');

        // validation
        if (!is_numeric($amount) || $amount <= 0) {
            return response()->json(['error' => 'Withdraw amount invalid'], 400);
        }

        DB::beginTransaction();
        try {
            // pessimistic lock
            $wallet = Wallet::where('id', $wallet_id)->lockForUpdate()->first();
            if (!$wallet) {
                // DB::commit();
                // return response()->json(['error' => 'Withdraw Wallet not found'], 404);
                throw new \Exception('Withdraw wallet not found');
            }

            // balance not enough
            if ($wallet->balance < $amount) {
                // DB::commit();
                // return response()->json(['error' => 'Wallet balance not enough'], 400);
                throw new \Exception('Wallet balance not enough');
            }

            // update withdraw amount
            $minus_balance = bcsub($wallet->balance, $amount, 2);
            $wallet->balance = $minus_balance;
            // $wallet->balance -= $amount;
            $wallet->save();

            // transaction record
            $wallet->transactions()->create(['type' => 'withdrawal', 'amount' => $amount]);

            // reetrieve latest wallet balance
            // $latest_wallet = Wallet::find($wallet_id);
            $latest_balance = $this->walletBalance($wallet_id, true);

            DB::commit();

            return response()->json([
                'message' => 'Withdraw success',
                'balance' => $latest_balance
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function walletBalance($wallet_id, $mode = false)
    {
        $wallet = Wallet::where('id', $wallet_id)->first();

        if (!$wallet) {
            return $mode ? null : response()->json(['error' => 'Find Wallet not found'], 404);
        }

        return $mode ? $wallet->balance : response()->json([
            'message' => 'Wallet balance',
            'balance' => $wallet->balance
        ], 200);
    }

    public function walletTransaction($wallet_id)
    {
        $wallet = Wallet::where('id', $wallet_id)->first();

        if (!$wallet) {
            return response()->json(['error' => 'Find wallet not found'], 404);
        }

        return response()->json([
            'message' => 'Wallet transaction',
            'transaction' => $wallet->transactions()->orderBy('created_at', 'desc')->get()
        ], 200);
    }
}
