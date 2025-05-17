<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductViewService
{
    const VIEWS_WEIGHT = 0.4; // 40% weight for views
    const SALES_WEIGHT = 0.6; // 60% weight for sales

    /**
     * Track a product view
     */
    public function trackView(Product $product, ?int $userId = null, ?string $ipAddress = null, ?string $userAgent = null)
    {
        try {
            DB::beginTransaction();

            // Create view record
            ProductView::create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'viewed_at' => now(),
            ]);

            // Update monthly views
            $this->updateMonthlyMetrics($product);

            DB::commit();
            Log::info("Product view tracked for product {$product->id}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to track product view: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update monthly metrics and popularity score
     */
    public function updateMonthlyMetrics(Product $product)
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Get monthly views
        $monthlyViews = ProductView::where('product_id', $product->id)
            ->whereBetween('viewed_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Get monthly sales
        $monthlySales = $product->orders()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Get monthly revenue
        $monthlyRevenue = $product->orders()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // Update product metrics
        $product->monthly_views = $monthlyViews;
        $product->monthly_sales = $monthlySales;
        $product->monthly_revenue = $monthlyRevenue;
        $product->last_viewed_at = $now;
        $product->popularity_score = $this->calculatePopularityScore($monthlyViews, $monthlySales);
        $product->save();
    }

    /**
     * Calculate product popularity score based on monthly views and sales
     * Formula: (monthly_views * VIEWS_WEIGHT) + (monthly_sales * SALES_WEIGHT)
     */
    protected function calculatePopularityScore(int $monthlyViews, int $monthlySales): int
    {
        return (int) (
            ($monthlyViews * self::VIEWS_WEIGHT) +
            ($monthlySales * self::SALES_WEIGHT)
        );
    }

    /**
     * Get popular products
     */
    public function getPopularProducts(int $limit = 10)
    {
        return Product::orderBy('popularity_score', 'desc')
            ->orderBy('monthly_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending products (products with high recent activity)
     */
    public function getTrendingProducts(int $limit = 10)
    {
        $startOfMonth = now()->startOfMonth();

        return Product::select('products.*')
            ->join('product_views', 'products.id', '=', 'product_views.product_id')
            ->where('product_views.viewed_at', '>=', $startOfMonth)
            ->groupBy('products.id')
            ->orderByRaw('COUNT(product_views.id) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get product view statistics
     */
    public function getProductViewStats(Product $product)
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $stats = [
            'monthly_views' => $product->monthly_views,
            'monthly_sales' => $product->monthly_sales,
            'monthly_revenue' => $product->monthly_revenue,
            'popularity_score' => $product->popularity_score,
            'last_viewed' => $product->last_viewed_at,
            'last_sold' => $product->last_sold_at,
        ];

        // Get daily views for the current month
        $dailyViews = ProductView::where('product_id', $product->id)
            ->whereBetween('viewed_at', [$startOfMonth, $endOfMonth])
            ->select(
                DB::raw('DATE(viewed_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        $stats['daily_views'] = $dailyViews;

        return $stats;
    }
} 