<?php

namespace App\Services;

use App\Models\Retailer;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletService
{
    /**
     * Credit a retailer's prepaid wallet atomically.
     */
    public function credit(string $retailerId, float $amount, string $note = 'Wallet Credit', string $by = 'System', string $mode = 'Wallet', ?string $ref = null)
    {
        if ($amount <= 0) {
            throw new Exception("Credit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($retailerId, $amount, $note, $by, $mode, $ref) {
            $retailer = Retailer::findOrFail($retailerId);
            $wallet = $retailer->wallet()->lockForUpdate()->firstOrCreate([
                'retailer_id' => $retailerId,
            ], [
                'balance' => 0.00,
                'currency' => 'INR',
            ]);

            $newBalance = $wallet->balance + $amount;
            $wallet->balance = $newBalance;
            $wallet->save();

            $txnId = 'TXN' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $transaction = WalletTransaction::create([
                'id' => $txnId,
                'retailer_id' => $retailerId,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'ref' => $ref ?? ('VHCR' . rand(10000000, 99999999)),
                'mode' => $mode,
                'note' => $note,
                'status' => 'success',
                'by' => $by,
                'created_at' => now(),
            ]);

            return [
                'wallet' => $wallet,
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * Debit a retailer's prepaid wallet atomically with row locking.
     */
    public function debit(string $retailerId, float $amount, string $note = 'Verification Charge', string $by = 'System', ?string $ref = null, string $mode = 'Wallet')
    {
        if ($amount <= 0) {
            throw new Exception("Debit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($retailerId, $amount, $note, $by, $ref, $mode) {
            $retailer = Retailer::findOrFail($retailerId);
            $wallet = $retailer->wallet()->lockForUpdate()->first();

            if (!$wallet || $wallet->balance < $amount) {
                $curBal = $wallet ? $wallet->balance : 0.00;
                throw new Exception("Insufficient prepaid wallet balance. Required: ₹{$amount}, Available: ₹{$curBal}. Please top up.");
            }

            $newBalance = $wallet->balance - $amount;
            $wallet->balance = $newBalance;
            $wallet->save();

            $txnId = 'TXN' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $transaction = WalletTransaction::create([
                'id' => $txnId,
                'retailer_id' => $retailerId,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'ref' => $ref ?? $txnId,
                'mode' => $mode,
                'note' => $note,
                'status' => 'success',
                'by' => $by,
                'created_at' => now(),
            ]);

            return [
                'wallet' => $wallet,
                'transaction' => $transaction,
            ];
        });
    }
}

