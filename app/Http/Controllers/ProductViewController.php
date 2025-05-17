<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductViewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductViewController extends Controller
{
    protected $viewService;

    public function __construct(ProductViewService $viewService)
    {
        $this->viewService = $viewService;
    }

    /**
     * Track a product view
     */
    public function trackView(Request $request, Product $product)
    {
        try {
            $this->viewService->trackView(
                $product,
                $request->user()?->id,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'message' => 'View tracked successfully',
                'product' => $product->fresh(['view_count', 'popularity_score', 'last_viewed_at'])
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to track product view: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to track view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular products
     */
    public function getPopularProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $products = $this->viewService->getPopularProducts($limit);

            return response()->json([
                'products' => $products
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get popular products: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get popular products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trending products
     */
    public function getTrendingProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $products = $this->viewService->getTrendingProducts($limit);

            return response()->json([
                'products' => $products
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get trending products: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get trending products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product view statistics
     */
    public function getProductStats(Request $request, Product $product)
    {
        try {
            $stats = $this->viewService->getProductViewStats($product);

            return response()->json([
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get product stats: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get product stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 