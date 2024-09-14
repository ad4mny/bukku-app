<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    // Create a new transaction (purchase or sale)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:purchase,sale',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();
        $today = now()->format('Y-m-d');

        // Check if user already has a transaction today
        $existingTransaction = Transaction::where('user_id', $userId)
            ->where('transaction_date', $today)
            ->first();

        if ($existingTransaction) {
            return response()->json([
                'success' => false,
                'message' => 'You can only have one transaction per day.',
            ], 400);
        }

        $product = Product::findOrFail($validated['product_id']);
        $totalAmount = $product->price * $validated['quantity'];

        $transaction = Transaction::create([
            'user_id' => $userId,
            'product_id' => $validated['product_id'],
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'price' => $product->price,
            'total_amount' => $totalAmount,
            'transaction_date' => $today,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction recorded successfully',
            'transaction' => $transaction->load('product'),
        ], 201);
    }

    // Retrieve list of transactions (with optional type filter)
    public function index(Request $request)
    {
        $type = $request->query('type');

        $query = Transaction::with('product')->where('user_id', Auth::id());

        if ($type) {
            $query->where('type', $type);
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }
}
