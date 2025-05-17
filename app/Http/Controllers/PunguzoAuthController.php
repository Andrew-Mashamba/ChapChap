<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PunguzoAuthController extends Controller
{
    protected $apiKey;
    protected $coreBaseUrl = 'https://punguzo.com/product/api';
    protected $paymentBaseUrl = 'https://mixx.punguzo.com:9443/api';

    public function __construct()
    {
        $this->apiKey = config('services.punguzo.api_key');
    }

    /**
     * Generate authentication token
     */
    public function generateTokenx()
    {
        // Check if valid token exists in cache
        if (Cache::has('punguzo_access_token')) {
            return response()->json([
                'token' => Cache::get('punguzo_access_token'),
                'from_cache' => true
            ]);
        }

        $response = Http::withHeaders([
            'API-Key' => $this->apiKey
        ])->post("{$this->coreBaseUrl}/generate_token");

        if ($response->successful()) {
            $data = $response->json();
            Cache::put('punguzo_access_token', $data['access_token'], $data['expires_in'] / 60);

            return response()->json($data);
        }

        return response()->json([
            'error' => 'Failed to generate token',
            'details' => $response->json()
        ], $response->status());
    }


    public function generateToken()
{
    // Check if valid token exists in cache
    if (Cache::has('punguzo_access_token')) {
        return response()->json([
            'token' => Cache::get('punguzo_access_token'),
            'from_cache' => true
        ]);
    }

    $response = Http::withHeaders([
        'API-Key' => $this->apiKey
    ])->post("{$this->coreBaseUrl}/generate_token");

    if ($response->successful()) {
        $data = $response->json();
        $token = $data['access_token'];
        $expiresIn = $data['expires_in'];

        // Cache it
        Cache::put('punguzo_access_token', $token, $expiresIn / 60);

        // Store in DB
        DB::table('access')->updateOrInsert(
            ['key' => 'punguzo_token'],
            [
                'value' => $token,
                'expires_at' => Carbon::now()->addSeconds($expiresIn),
                'updated_at' => now()
            ]
        );

        return response()->json($data);
    }

    return response()->json([
        'error' => 'Failed to generate token',
        'details' => $response->json()
    ], $response->status());
}




    /**
     * Get the current access token
     */
    protected function getAccessToken()
    {
        if (!Cache::has('punguzo_access_token')) {
            $this->generateToken();
        }

        return Cache::get('punguzo_access_token');
    }
}
