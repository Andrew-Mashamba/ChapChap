<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'monthly_views',
        'monthly_sales',
        'monthly_revenue',
        'popularity_score',
        'last_viewed_at',
        'last_sold_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'monthly_revenue' => 'decimal:2',
        'last_viewed_at' => 'datetime',
        'last_sold_at' => 'datetime',
    ];

    /**
     * Get the product views for the product.
     */
    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class);
    }

    /**
     * Get the orders for the product.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
} 