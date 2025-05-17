<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductRecommendationService
{
    protected $productViewService;
    protected $cacheTime = 3600; // 1 hour cache

    public function __construct(ProductViewService $productViewService)
    {
        $this->productViewService = $productViewService;
    }

    /**
     * Get personalized product recommendations for a user
     */
    public function getPersonalizedRecommendations(User $user, int $limit = 20)
    {
        $cacheKey = "user_recommendations_{$user->id}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($user, $limit) {
            // Get user's viewing and purchase history
            $viewedCategories = $this->getUserViewedCategories($user);
            $purchasedCategories = $this->getUserPurchasedCategories($user);

            // Combine categories with weights
            $preferredCategories = array_merge(
                array_fill_keys($viewedCategories, 0.3),    // 30% weight for viewed
                array_fill_keys($purchasedCategories, 0.7)  // 70% weight for purchased
            );

            // Get products with popularity scores
            return Product::select('products.*')
                ->when(!empty($preferredCategories), function ($query) use ($preferredCategories) {
                    // Add category preference to score
                    return $query->selectRaw('
                        products.*,
                        CASE 
                            WHEN products.category_id IN (' . implode(',', array_keys($preferredCategories)) . ')
                            THEN popularity_score * 1.2
                            ELSE popularity_score
                        END as adjusted_score
                    ');
                })
                ->orderBy('adjusted_score', 'desc')
                ->orderBy('monthly_sales', 'desc')
                ->orderBy('monthly_revenue', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get trending products based on recent activity
     */
    public function getTrendingProducts(int $limit = 20)
    {
        $cacheKey = 'trending_products';
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($limit) {
            return Product::select('products.*')
                ->selectRaw('
                    (popularity_score * 0.4) + 
                    (monthly_sales * 0.4) + 
                    (monthly_revenue * 0.2) as trending_score
                ')
                ->orderBy('trending_score', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get popular products in a specific category
     */
    public function getPopularProductsByCategory(int $categoryId, int $limit = 20)
    {
        $cacheKey = "popular_products_category_{$categoryId}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($categoryId, $limit) {
            return Product::where('category_id', $categoryId)
                ->orderBy('popularity_score', 'desc')
                ->orderBy('monthly_sales', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get similar products based on a product
     */
    public function getSimilarProducts(Product $product, int $limit = 10)
    {
        $cacheKey = "similar_products_{$product->id}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($product, $limit) {
            return Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->orderBy('popularity_score', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get categories viewed by user
     */
    protected function getUserViewedCategories(User $user): array
    {
        return DB::table('product_views')
            ->join('products', 'product_views.product_id', '=', 'products.id')
            ->where('product_views.user_id', $user->id)
            ->where('product_views.viewed_at', '>=', now()->subDays(30))
            ->select('products.category_id')
            ->distinct()
            ->pluck('category_id')
            ->toArray();
    }

    /**
     * Get categories purchased by user
     */
    protected function getUserPurchasedCategories(User $user): array
    {
        return DB::table('orders')
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->where('orders.user_id', $user->id)
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->select('products.category_id')
            ->distinct()
            ->pluck('category_id')
            ->toArray();
    }
} 