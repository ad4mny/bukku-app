<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Reference to the user who made the transaction
            $table->foreignId('product_id')->constrained()->onDelete('cascade'); 
            $table->enum('type', ['purchase', 'sale']); // Purchase or sale
            $table->integer('quantity');
            $table->decimal('price', 10, 2); 
            $table->decimal('total_amount', 10, 2);
            $table->date('transaction_date'); 
            $table->timestamps();

            // Ensure only one transaction per day for a user
            $table->unique(['user_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
