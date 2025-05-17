<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance(Request $request)
    {
        $member = $request->user();
        return response()->json([
            'data' => [
                'balance' => $member->commission_balance,
                'currency' => 'TZS',
            ]
        ]);
    }

    public function addFunds(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'payment_method' => 'required|string|in:mobile_money,bank_transfer',
        ]);

        $member = $request->user();

        // Create transaction record
        $transaction = Transaction::create([
            'member_id' => $member->id,
            'type' => 'deposit',
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'status' => 'pending',
            'description' => 'Wallet top-up',
        ]);

        // Initiate payment process based on payment method
        if ($validated['payment_method'] === 'mobile_money') {
            // Integrate with mobile money provider
            // This is a placeholder for the actual integration
            $paymentResponse = [
                'transaction_id' => $transaction->id,
                'payment_url' => 'https://payment-provider.com/pay/' . $transaction->id,
            ];
        } else {
            // Bank transfer instructions
            $paymentResponse = [
                'transaction_id' => $transaction->id,
                'bank_details' => [
                    'bank_name' => $member->bank_name,
                    'account_name' => $member->bank_account_name,
                    'account_number' => $member->bank_account_number,
                ],
            ];
        }

        return response()->json([
            'message' => 'Payment initiated successfully',
            'data' => $paymentResponse
        ]);
    }

    public function transactions(Request $request)
    {
        $query = Transaction::where('member_id', $request->user()->id);

        $transactions = $query->paginate($request->input('limit', 20));

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
