<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\ProductViewService;
use App\Services\MLMPointService;
use App\Services\MLMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private $productViewService;
    private $mlmPointService;
    private $mlmNotificationService;

    public function __construct(
        ProductViewService $productViewService,
        MLMPointService $mlmPointService,
        MLMNotificationService $mlmNotificationService
    ) {
        $this->productViewService = $productViewService;
        $this->mlmPointService = $mlmPointService;
        $this->mlmNotificationService = $mlmNotificationService;
    }

    /**
     * Complete an order and trigger commission calculations
     */
    public function completeOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::with(['product', 'user'])->findOrFail($request->order_id);
            
            // Update order status
            $order->status = 'completed';
            $order->payment_status = 'paid';
            $order->save();

            // Update product metrics
            $product = $order->product;
            $product->last_sold_at = now();
            $product->save();

            // Update popularity score
            $this->productViewService->updateMonthlyMetrics($product);

            // Calculate commissions
            $this->mlmPointService->addPoints($order->user_id, $order->total_amount);
            
            // Send notifications
            $this->mlmNotificationService->sendCommissionAlert(
                $order->user_id,
                $order->total_amount,
                $order->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Order completed successfully',
                'order' => $order->fresh(['product', 'user']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete order: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to complete order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order statistics
     */
    public function getOrderStats(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        try {
            $product = Product::findOrFail($request->product_id);
            
            $stats = [
                'total_orders' => $product->orders()->count(),
                'total_revenue' => $product->orders()->sum('total_amount'),
                'monthly_sales' => $product->monthly_sales,
                'monthly_revenue' => $product->monthly_revenue,
                'popularity_score' => $product->popularity_score,
                'last_sold_at' => $product->last_sold_at,
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Failed to get order stats: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to get order statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 