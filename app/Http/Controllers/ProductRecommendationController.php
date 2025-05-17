<?php

namespace App\Http\Controllers;

use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductRecommendationController extends Controller
{
    protected $recommendationService;

    public function __construct(ProductRecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Get personalized recommendations for the authenticated user
     */
    public function getPersonalizedRecommendations(Request $request)
    {
        $user = Auth::user();
        $limit = $request->input('limit', 20);

        $recommendations = $this->recommendationService->getPersonalizedRecommendations($user, $limit);

        return response()->json([
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Get trending products
     */
    public function getTrendingProducts(Request $request)
    {
        $limit = $request->input('limit', 20);

        $trendingProducts = $this->recommendationService->getTrendingProducts($limit);

        return response()->json([
            'trending_products' => $trendingProducts,
        ]);
    }

    /**
     * Get popular products by category
     */
    public function getPopularProductsByCategory(Request $request, int $categoryId)
    {
        $limit = $request->input('limit', 20);

        $popularProducts = $this->recommendationService->getPopularProductsByCategory($categoryId, $limit);

        return response()->json([
            'popular_products' => $popularProducts,
        ]);
    }

    /**
     * Get similar products
     */
    public function getSimilarProducts(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);
        $limit = $request->input('limit', 10);

        $similarProducts = $this->recommendationService->getSimilarProducts($product, $limit);

        return response()->json([
            'similar_products' => $similarProducts,
        ]);
    }
} 