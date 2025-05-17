<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('merchant_name', 'like', "%{$search}%");
            });
        }

        // Paginate results
        $products = $query->paginate($request->input('limit', 20));

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json(['data' => $product]);
    }

    public function groupedByCategory()
    {
        $products = Product::all()->groupBy('category');

        $grouped = [];
        foreach ($products as $category => $items) {
            $grouped[] = [
                'category' => $category,
                'products' => $items,
            ];
        }

        return response()->json(['data' => $grouped]);
    }
}
