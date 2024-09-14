<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'product_id', 'type', 'quantity', 'price', 'total_amount', 'transaction_date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Retrieve only purchases
    public function scopePurchases($query)
    {
        return $query->where('type', 'purchase');
    }

    // Retrieve only sales
    public function scopeSales($query)
    {
        return $query->where('type', 'sale');
    }
}
