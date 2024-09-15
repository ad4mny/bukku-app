<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SaleCosting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            $this->updateInventory($validated['product_id'], $validated['quantity'], $validated['type'], $userId);

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
            $transactionDate = Carbon::parse($transaction->transaction_date)->addDay()->toDateString();

            // Check if the new date is available
            while (Transaction::where('user_id', $userId)->where('transaction_date', $transactionDate)->exists()) {
                $transactionDate = Carbon::parse($transactionDate)->addDay()->toDateString();
            }

            $transaction->transaction_date = $transactionDate;
            $transaction->save();
        }
    }

    private function updateInventory($productId, $quantity, $transactionType, $userId)
    {
        $saleCosting = SaleCosting::where('user_id', $userId)->first();
        $product = Product::find($productId);

        if (!$product) {
            throw new \Exception('Product not found.');
        }

        if ($transactionType === 'sale' && $product->quantity < $quantity) {
            throw new \Exception('Not enough quantity available for this sale.');
        }

        $newQuantity = $quantity;
        $newValue = $product->price * $quantity;

        if ($saleCosting) { // Perform calculations if Sale Costing exists
            if ($transactionType === 'purchase') {
                $newQuantity = $saleCosting->total_quantity + $quantity;
                $newValue = $saleCosting->total_value + ($product->price * $quantity);
            } else {
                // Ensure division by zero will not occur
                if ($saleCosting->total_quantity > 0) {
                    $averageCost = $saleCosting->total_value / $saleCosting->total_quantity;
                } else {
                    throw new \Exception('Cannot calculate average cost: total quantity is zero.');
                }

                $totalWAC = $averageCost * $quantity;
                $newQuantity = $saleCosting->total_quantity - $quantity;
                $newValue = $saleCosting->total_value - $totalWAC;

                if ($newQuantity < 0) {
                    throw new \Exception('Not enough inventory to complete the sale.');
                }
            }
        }

        // Wrap in transactions just in case...
        DB::transaction(function () use ($saleCosting, $product, $transactionType, $quantity, $userId, $newQuantity, $newValue) {
            SaleCosting::updateOrCreate(['user_id' => $userId], [
                'total_quantity' => $newQuantity,
                'total_value' => $newValue,
            ]);

            if ($transactionType === 'sale') {
                $product->quantity = $product->quantity - $quantity;
            } else {
                $product->quantity = $product->quantity + $quantity;
            }

            $product->save();
        });
    }
}
