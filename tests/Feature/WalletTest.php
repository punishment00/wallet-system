<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Wallet;
use App\Jobs\RebateJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\Utils;

class WalletTest extends TestCase
{
    // use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    // public function test_example(): void
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }

    /**
     * 1.	Deposit with Rebate: Ensure depositing funds calculates and credits the rebate correctly.
     */
    public function testDepositWithRebate()
    {
        // create test wallet
        $wallet = Wallet::factory()->create(['balance' => 100]);
        $currentWallet = Wallet::find($wallet->id);
        Log::info('testDepositWithRebate: balance before deposit: ' . $currentWallet->balance);

        $amount = 100;
        $rebate_amount = bcmul($amount, '0.01', 2);
        $deposit_with_rebate = bcadd($rebate_amount, $amount, 2);
        $total_amount = bcadd($deposit_with_rebate, $currentWallet->balance, 2);

        // deposit request
        $response = $this->postJson('/api/wallet/deposit', [
            'wallet_id' => $wallet->id,
            'amount' => $amount
        ]);

        // check response is 200
        $response->assertStatus(200);

        // check data
        $response->assertJson([
            'message' => 'Deposit success',
            'balance' => $total_amount
        ]);

        // check latest balance
        $updatedWallet = Wallet::find($wallet->id);
        $this->assertEquals($total_amount, $updatedWallet->balance);
        Log::info('testDepositWithRebate: Expected balance after deposit: ' . $updatedWallet->balance);
        Log::info('testDepositWithRebate: Actual balance after deposit: ' . $total_amount);

        // check transaction
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $amount
        ]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'rebate',
            'amount' => $rebate_amount
        ]);
    }

    /**
     * 2.	Concurrent Deposits with Rebate: Test concurrent deposits to verify correct balance updates and rebate calculations.
     */
    public function testConcurrentDepositsWithRebate()
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $total_request = 50;
        $amount = 100;
        $rebate_amount = bcmul($amount, '0.01', 2);
        $deposit_with_rebate = bcadd($rebate_amount, $amount, 2);
        $total_deposit_with_rebate = bcmul($deposit_with_rebate, $total_request, 2);
        $total_amount = bcadd($total_deposit_with_rebate, $wallet->balance, 2);

        // concurrent deposit request
        // $responses = collect(range(1, $total_request))->map(function () use ($wallet, $amount) {
        //     return $this->postJson('/api/wallet/deposit', [
        //         'wallet_id' => $wallet->id,
        //         'amount' => $amount
        //     ]);
        // });

        // $responses->each(function ($response) {
        //     $response->assertStatus(200);
        // });

        // use guzzle
        $client = new Client([
            'base_uri' => 'http://wallet-system.test/'
        ]);
        $promises = [];
        for ($i = 0; $i < $total_request; $i++) {
            $promises[] = $client->postAsync('/api/wallet/deposit', [
                'json' => [
                    'wallet_id' => $wallet->id,
                    'amount' => $amount
                ]
            ]);
        }
        // Log::info('testConcurrentDepositsWithRebate: id: ' . $wallet->id);
        $responses = Promise\Utils::unwrap($promises);

        foreach ($responses as $response) {
            $this->assertEquals(200, $response->getStatusCode());
        }

        // check latest balance
        $updatedWallet = Wallet::find($wallet->id);
        // Log::info('testConcurrentDepositsWithRebate: Actual balance after deposit: ' . $updatedWallet->balance);
        // Log::info('testConcurrentDepositsWithRebate: Expected balance after deposit: ' . $total_amount);
        $this->assertEquals($total_amount, $updatedWallet->balance);

        // // check transaction 
        // $this->assertDatabaseCount('transactions', $total_request * 2);
    }


    /**
     * normal successful withdraw
     */
    public function testWithdrawSuccess()
    {
        $wallet = Wallet::factory()->create(['balance' => 200]);
        $amount = 100;

        $response = $this->postJson('/api/wallet/withdraw', [
            'wallet_id' => $wallet->id,
            'amount' => $amount
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Withdraw success',
            'balance' => bcsub($wallet->balance, $amount, 2)
        ]);

        $updatedWallet = Wallet::find($wallet->id);
        $this->assertEquals(bcsub($wallet->balance, $amount, 2), $updatedWallet->balance);
        Log::info('testWithdrawSuccess: Expected balance after witthdraw: ' . $updatedWallet->balance);
        Log::info('testWithdrawSuccess: Actual balance after witthdraw: ' . bcsub($wallet->balance, $amount, 2));

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'amount' => $amount
        ]);
    }

    /**
     * withdraw insufficient amount
     */
    public function testWithdrawInsufficientBalance()
    {
        $this->artisan('migrate');

        $wallet = Wallet::factory()->create(['balance' => 100]);
        $amount = 200;

        $response = $this->postJson('/api/wallet/withdraw', [
            'wallet_id' => $wallet->id,
            'amount' => $amount
        ]);

        $response->assertStatus(400);

        $response->assertJson([
            'error' => 'Wallet balance not enough'
        ]);
    }

    /**
     * concurrency withdraw amount
     */
    public function testConcurrentWithdraw()
    {
        $wallet = Wallet::factory()->create(['balance' => 500]);

        $amount = 100;
        $total_request = 5;

        $responses = collect(range(1, $total_request))->map(function () use ($wallet, $amount) {
            return $this->postJson('/api/wallet/withdraw', [
                'wallet_id' => $wallet->id,
                'amount' => $amount
            ]);
        });

        $responses->each(function ($response) {
            $response->assertStatus(200);
        });

        $total_withdraw = $amount * $total_request;
        $expected_balance = bcsub($wallet->balance, $total_withdraw, 2);

        $updatedWallet = Wallet::find($wallet->id);
        $this->assertEquals($expected_balance, $updatedWallet->balance);
        Log::info('testConcurrentWithdraw: Expected balance after withdraw: ' . $updatedWallet->balance);
        Log::info('testConcurrentWithdraw: Actual balance after withdraw: ' . $expected_balance);

        // // check transaction 
        // $this->assertDatabaseCount('transactions', $total_request);
    }
}
