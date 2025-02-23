<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $wallet = Wallet::where('id', $wallet_id)->lockForUpdate()->first();
            if (!$wallet) {
                return response()->json(['error' => 'Deposit Wallet not found'], 404);
            }

            // update deposit amount
            $add_balance = bcadd($wallet->balance, $amount, 2);
            $wallet->balance = $add_balance;
            // $wallet->balance += $amount;
            $wallet->save();

            // reetrieve latest wallet balance
            $latest_wallet = Wallet::find($wallet_id);

            DB::commit();

            return response()->json([
                'message' => 'Deposit success',
                'balance' => $latest_wallet->balance
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
                return response()->json(['error' => 'Withdraw Wallet not found'], 404);
            }

            // balance not enough
            if ($wallet->balance < $amount) {
                return response()->json(['error' => 'Wallet balance not enough'], 400);
            }

            // update withdraw amount
            $minus_balance = bcsub($wallet->balance, $amount, 2);
            $wallet->balance = $minus_balance;
            // $wallet->balance -= $amount;
            $wallet->save();

            // reetrieve latest wallet balance
            $latest_wallet = Wallet::find($wallet_id);

            DB::commit();

            return response()->json([
                'message' => 'Withdraw success',
                'balance' => $latest_wallet->balance
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'ERROR: ' . $e->getMessage()
            ], 400);
        }
    }

    public function walletBalance($wallet_id)
    {
        $wallet = Wallet::where('user_id', $wallet_id)->first();

        if (!$wallet) {
            return response()->json(['error' => 'Find wallet not found'], 404);
        }

        return response()->json([
            'message' => 'Wallet balance',
            'balance' => $wallet->balance
        ], 200);
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
