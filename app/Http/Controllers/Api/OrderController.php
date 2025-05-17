<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::where('member_id', $request->user()->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->input('limit', 20));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string',
            'payment_method' => 'required|string|in:mobile_money,bank_transfer,cash',
            'delivery_method' => 'required|string|in:pickup,delivery',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Check if product is available
        if ($product->total_item_available < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient stock available'
            ], 400);
        }

        // Calculate total price
        $totalPrice = $product->selling_price * $validated['quantity'];

        // Add delivery fee if delivery method is selected
        if ($validated['delivery_method'] === 'delivery') {
            $totalPrice += $product->within_region_delivery_fee;
        }

        $order = Order::create([
            'member_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'total_price' => $totalPrice,
            'delivery_address' => $validated['delivery_address'],
            'payment_method' => $validated['payment_method'],
            'delivery_method' => $validated['delivery_method'],
            'status' => 'pending',
        ]);

        // Update product stock
        $product->decrement('total_item_available', $validated['quantity']);

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }

    public function show($id)
    {
        $order = Order::where('member_id', request()->user()->id)
                     ->findOrFail($id);

        return response()->json(['data' => $order]);
    }
}
