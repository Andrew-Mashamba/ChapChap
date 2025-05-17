<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class PunguzoProductController extends PunguzoAuthController
{
    /**
     * Get products with optional filtering and pagination
     */
    public function getProducts(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:10|multiple_of:10',
            'offset' => 'sometimes|integer',
            'filter_type' => 'sometimes|in:recently_sold,wholesale,big_discount,recently_purchased,punguzo_special'
        ]);

        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        if ($offset % $limit !== 0) {
            return response()->json([
                'error' => 'Offset must be a multiple of limit.'
            ], 422);
        }

        $queryParams = [
            'limit' => $limit,
            'offset' => $offset,
            'filter_type' => $validated['filter_type'] ?? 'recently_sold'
        ];

        $response = $this->makeProductRequest($queryParams, $request);

        if ($response->status() === 401) {
            $this->generateToken();
            $response = $this->makeProductRequest($queryParams, $request);
        }

        if ($response->successful()) {
            $data = $response->json();
            $products = $data['results'] ?? [];

            $inserted = 0;














            $chunkSize = 500; // Tune based on memory and query size
            $inserted = 0;
            $updated = 0;

            collect($products)->chunk($chunkSize)->each(function ($productChunk) use (&$inserted, &$updated) {
                $externalIds = $productChunk->pluck('id')->all();

                // Fetch existing records once for the chunk
                $existingProducts = DB::table('products')
                    ->whereIn('external_id', $externalIds)
                    ->get()
                    ->keyBy('external_id');

                $now = now();
                $insertData = [];
                $updateData = [];

                foreach ($productChunk as $product) {
                    $productData = [
                        'external_id' => $product['id'],
                        'name' => $product['name'],
                        'description' => $product['description'] ?? null,
                        'category' => $product['category'] ?? null,
                        'merchant_name' => $product['merchant_name'] ?? null,
                        'pickup_locations' => Arr::get($product, 'merchant.pickup_locations'),
                        'shop_region' => $product['shop_region'] ?? null,
                        'region' => Arr::get($product, 'merchant.region'),
                        'selling_price' => $product['selling_price'] ?? null,
                        'original_price' => $product['original_price'] ?? null,
                        'discount_price' => $product['discount_price'] ?? null,
                        'total_item_available' => $product['total_item_available'] ?? null,
                        'within_region_delivery_fee' => $product['within_region_delivery_fee'] ?? 0,
                        'outside_region_delivery_fee' => $product['outside_region_delivery_fee'] ?? 0,
                        'is_delivery_allowed' => $product['is_delivery_allowed'] ? 1 : 0,
                        'media_json' => json_encode($product['media'] ?? []),
                        'raw_json' => json_encode($product),
                        'updated_at' => $now,
                    ];

                    if (!isset($existingProducts[$product['id']])) {
                        $productData['created_at'] = $now;
                        $insertData[] = $productData;
                    } else {
                        $existing = $existingProducts[$product['id']];
                        $hasChanges = false;

                        foreach ($productData as $key => $value) {
                            if ($key === 'updated_at') continue;
                            if ($existing->$key != $value) {
                                $hasChanges = true;
                                break;
                            }
                        }

                        if ($hasChanges) {
                            $productData['id'] = $existing->id;
                            $updateData[] = $productData;
                        }
                    }
                }

                // Insert
                if (!empty($insertData)) {
                    DB::table('products')->insert($insertData);
                    $inserted += count($insertData);
                }

                // Update
                if (!empty($updateData)) {
                    $caseStatements = [];
                    $ids = [];
                    $params = [];

                    $columns = array_keys($updateData[0]);
                    $columns = array_diff($columns, ['id']); // Exclude ID from update columns

                    foreach ($columns as $column) {
                        $cases = [];
                        foreach ($updateData as $row) {
                            $cases[] = "WHEN {$row['id']} THEN ?";
                            $params[] = $row[$column];
                        }
                        $caseStatements[] = "`$column` = CASE `id` " . implode(' ', $cases) . " END";
                    }

                    $ids = array_column($updateData, 'id');
                    $params = array_merge($params, $ids);

                    $query = "UPDATE products SET " . implode(', ', $caseStatements) . " WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
                    DB::statement($query, $params);

                    $updated += count($updateData);
                }
            });

            info("Sync complete: Inserted {$inserted}, Updated {$updated}");







            return response()->json([
                'message' => 'Products fetched and saved.',
                'inserted_count' => $inserted,
                'updates_count' => $updated
            ]);
        }

        return $this->handleApiError($response);
    }

    /**
     * Send product request to the external API
     */
    protected function makeProductRequest(array $queryParams, Request $request)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Cache-Control' => $request->header('Cache-Control', 'public, max-age=300'),
            'Accept' => 'application/json'
        ])->get("{$this->coreBaseUrl}/get_products", $queryParams);
    }

    /**
     * Handle API errors
     */
    protected function handleApiError($response)
    {
        $status = $response->status();
        $errorData = $response->json() ?? ['message' => $response->body()];

        Log::warning('Punguzo API error', [
            'status' => $status,
            'error' => $errorData
        ]);

        return response()->json([
            'error' => 'API request failed',
            'status_code' => $status,
            'details' => $errorData
        ], $status);
    }
}
