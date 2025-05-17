<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PunguzoPaymentController extends PunguzoAuthController
{
    /**
     * Initiate debit request
     */
    public function debitRequest(Request $request)
    {
        Log::info('PunguzoPaymentController::debitRequest - Starting debit request', ['request' => $request->all()]);
        $validator = Validator::make($request->all(), [
            'billAmount' => 'required|numeric|min:0',
            'referenceID' => 'required|string|max:50',
            'customerDetails.phone' => 'required|string|min:9',
            'customerDetails.full_name' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.delivery_fee' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::error('PunguzoPaymentController::debitRequest - Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'status' => 'validation_error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Validate bill amount calculation
        $calculatedAmount = collect($request->items)->sum(function ($item) {
            return ($item['selling_price'] * $item['quantity']) + $item['delivery_fee'];
        });

        if ($calculatedAmount != $request->billAmount) {
            Log::error('PunguzoPaymentController::debitRequest - Bill amount mismatch', ['billAmount' => $request->billAmount, 'calculatedAmount' => $calculatedAmount]);
            return response()->json([
                'status' => 'validation_error',
                'error' => "billAmount ({$request->billAmount}) ≠ total ($calculatedAmount)"
            ], 400);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json'
        ])->post("{$this->paymentBaseUrl}/debit_request", $request->all());

        if ($response->successful()) {
            Log::info('PunguzoPaymentController::debitRequest - Debit request successful', ['response' => $response->json()]);
            return response()->json($response->json());
        }

        Log::error('PunguzoPaymentController::debitRequest - API error', ['response' => $response->body()]);
        return $this->handleApiError($response);
    }

    /**
     * Handle payment callback (webhook)
     */
    public function paymentCallback(Request $request)
    {
        Log::info('PunguzoPaymentController::paymentCallback - Starting payment callback', ['request' => $request->all()]);
        $data = $request->validate([
            'Amount' => 'required|numeric',
            'MNOTransactionID' => 'sometimes|string',
            'ReferenceID' => 'required|string',
            'Description' => 'required|string',
            'Status' => 'required|boolean'
        ]);

        // Process the payment status update
        // You would typically update your order status here
        $status = $data['Status'] ? 'completed' : 'failed';

        // Example: Update order in database
        // Order::where('reference_id', $data['ReferenceID'])
        //     ->update(['payment_status' => $status]);

        Log::info('PunguzoPaymentController::paymentCallback - Payment callback processed', ['status' => $status]);
        return response()->json(['status' => 'success']);
    }


    /**
 * Handle API errors consistently across all controllers
 */
protected function handleApiError($response)
{
    Log::error('PunguzoPaymentController::handleApiError - Handling API error', ['statusCode' => $response->status(), 'errorData' => $response->json()]);
    $statusCode = $response->status();
    $errorData = $response->json();

    $errorMessage = $errorData['error'] ?? 'API request failed';
    $errorDetails = $errorData['details'] ?? $errorData['message'] ?? $response->body();

    // Special handling for rate limiting
    if ($statusCode === 429) {
        Log::warning('PunguzoPaymentController::handleApiError - Rate limit exceeded', ['retry_after' => $response->header('Retry-After')]);
        return response()->json([
            'error' => 'Rate limit exceeded',
            'retry_after' => $response->header('Retry-After'),
            'limits' => [
                'limit' => $response->header('X-RateLimit-Limit'),
                'remaining' => $response->header('X-RateLimit-Remaining'),
                'reset' => $response->header('X-RateLimit-Reset')
            ]
        ], 429);
    }

    // Handle token expiration specifically
    if ($statusCode === 401 && str_contains($errorDetails, 'expired')) {
        Log::warning('PunguzoPaymentController::handleApiError - Token expired, attempting refresh');
        // Attempt to refresh token automatically
        $newToken = $this->generateToken();

        if ($newToken->getStatusCode() === 200) {
            Log::info('PunguzoPaymentController::handleApiError - Token refreshed successfully');
            // Return instruction to retry with new token
            return response()->json([
                'error' => 'Token expired',
                'action' => 'Token has been refreshed automatically',
                'retry' => true,
                'new_token' => $newToken->getData()->access_token
            ], 401);
        }
    }

    // Standard error response
    $responseData = [
        'error' => $errorMessage,
        'status_code' => $statusCode,
        'details' => $errorDetails,
        'timestamp' => now()->toDateTimeString()
    ];

    // Include reference ID if available
    if (isset($errorData['reference_id'])) {
        $responseData['reference_id'] = $errorData['reference_id'];
    }

    Log::error('PunguzoPaymentController::handleApiError - Standard error response', ['responseData' => $responseData]);
    return response()->json($responseData, $statusCode);
}


}
