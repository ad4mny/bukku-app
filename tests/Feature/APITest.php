<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class APITest extends TestCase
{
    /**
     * Test user registration
     */
    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->dump();
        $response->assertStatus(201);
    }

    /**
     * Test user login
     */
    public function test_user_can_login()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->dump();
        $response->assertStatus(200);

        // Store token for further tests
        $this->token = $response->json('token');
    }

    /**
     * Test recording a purchase transaction
     */
    public function test_user_can_create_purchase_transaction()
    {
        $this->test_user_can_login();

        $product = Product::first();

        $response = $this->postJson('/api/transactions', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'purchase'
        ], ['Authorization' => "Bearer {$this->token}"]);

        $response->dump();
        $response->assertStatus(201);

        // Check if the transaction exists in the database
        $this->assertDatabaseHas('transactions', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'purchase',
        ]);
    }

    /**
     * Test recording a sale transaction
     */
    public function test_user_can_create_sale_transaction()
    {
        $this->test_user_can_login();

        $product = Product::first();

        // First, make a purchase to have stock
        $this->postJson('/api/transactions', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'purchase'
        ], ['Authorization' => "Bearer {$this->token}"]);

        // Create a sale transaction
        $response = $this->postJson('/api/transactions', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'sale'
        ], ['Authorization' => "Bearer {$this->token}"]);

        $response->dump();
        $response->assertStatus(201);

        // Check if the sale exists in the database
        $this->assertDatabaseHas('transactions', [
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'sale',
        ]);
    }

    /**
     * Test updating a transaction
     */
    public function test_user_can_update_transaction()
    {
        $this->test_user_can_login();

        $product = Product::first();

        // Create a transaction
        $transaction = Transaction::create([
            'user_id' => User::first()->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'purchase'
        ]);

        $quantity = 2;
        $price = $product->price * 2;

        // Update the transaction
        $response = $this->putJson("/api/transactions/{$transaction->id}", [
            'quantity' => $quantity,
            'price' => $price,
            'transaction_date' => now()->toDateString() 
        ], ['Authorization' => "Bearer {$this->token}"]);

        $response->dump();
        $response->assertStatus(200);

        // Check if the transaction is updated in the database
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    /**
     * Test deleting a transaction
     */
    public function test_user_can_delete_transaction()
    {
        $this->test_user_can_login();

        $product = Product::first();

        // Create a transaction
        $transaction = Transaction::create([
            'user_id' => User::first()->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'type' => 'purchase'
        ]);

        // Delete the transaction
        $response = $this->deleteJson("/api/transactions/{$transaction->id}", [], ['Authorization' => "Bearer {$this->token}"]);

        $response->dump();
        $response->assertStatus(200);

        // Check if the transaction is deleted from the database
        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
        ]);
    }
}
