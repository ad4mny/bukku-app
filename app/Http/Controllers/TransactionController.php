<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    // Create a new transaction (purchase or sale)
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'type' => 'required|in:purchase,sale',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'transaction_date' => 'nullable|date',
            ]);

            $userId = Auth::id();

            $newTransactionDate = $validated['transaction_date'] ?? Carbon::now()->toDateString();

            // Adjust the transactions if there is already a transaction on this date or later
            $this->adjustTransactionDates($userId, $newTransactionDate);

            $product = Product::findOrFail($validated['product_id']);
            $totalAmount = $product->price * $validated['quantity'];

            $transaction = Transaction::create([
                'user_id' => $userId,
                'product_id' => $validated['product_id'],
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'price' => $product->price,
                'total_amount' => $totalAmount,
                'transaction_date' => $newTransactionDate,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction recorded successfully.',
                'transaction' => $transaction->load('product'),
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Retrieve list of transactions (with optional type filter)
    public function index(Request $request)
    {
        $type = $request->query('type');

        $query = Transaction::with('product');

        if ($type) {
            $query->where('type', $type);
        }

        $transactions = $query->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'type' => 'required|in:purchase,sale',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
                'transaction_date' => 'nullable|date',
            ]);

            $userId = Auth::id();
            $transaction = Transaction::where('id', $id)->where('user_id', $userId)->firstOrFail();
            $oldTransactionDate = $transaction->transaction_date;
            $newTransactionDate = $validated['transaction_date'] ?? Carbon::now()->toDateString();

            if ($oldTransactionDate != $newTransactionDate) {
                $this->adjustTransactionDates($userId, $newTransactionDate, $oldTransactionDate);
            }

            $product = Product::findOrFail($validated['product_id']);
            $totalAmount = $product->price * $validated['quantity'];

            $transaction->update([
                'product_id' => $validated['product_id'],
                'type' => $validated['type'],
                'quantity' => $validated['quantity'],
                'price' => $product->price,
                'total_amount' => $totalAmount,
                'transaction_date' => $newTransactionDate,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully.',
                'transaction' => $transaction->load('product'),
            ], 200);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully.'
        ], 200);
    }

    private function adjustTransactionDates($userId, $newTransactionDate, $oldTransactionDate = null)
    {
        if ($oldTransactionDate && $newTransactionDate < $oldTransactionDate) { // Adjust only if the new date is after the old date or if the old date is null
            $existingTransactions = Transaction::where('user_id', $userId)
                ->whereBetween('transaction_date', [$newTransactionDate, $oldTransactionDate])
                ->orderBy('transaction_date', 'asc')
                ->get();
        } else { // Adjust transactions on or after the new date
            $existingTransactions = Transaction::where('user_id', $userId)
                ->where('transaction_date', '>=', $newTransactionDate)
                ->orderBy('transaction_date', 'asc')
                ->get();
        }

        // Shift dates forward by 1 day
        foreach ($existingTransactions as $transaction) {
            $transaction->transaction_date = $transaction->transaction_date->addDay();
            $transaction->save();
        }
    }
}
