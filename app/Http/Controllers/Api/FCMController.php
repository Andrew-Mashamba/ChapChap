<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\FCMService;

class FCMController extends Controller
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function updateToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        try {
            $member = $request->user();
            $member->update([
                'fcm_token' => $request->fcm_token,
            ]);

            Log::info('FCM token updated', [
                'member_id' => $member->id,
                'token' => $request->fcm_token,
            ]);

            return response()->json([
                'message' => 'FCM token updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update FCM token', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Failed to update FCM token',
            ], 500);
        }
    }

    public function subscribeToTopic(Request $request)
    {
        $request->validate([
            'topic' => 'required|string',
        ]);

        try {
            $member = $request->user();
            if (!$member->fcm_token) {
                return response()->json([
                    'message' => 'FCM token not found',
                ], 400);
            }

            $this->fcmService->subscribeToTopic($request->topic, $member->fcm_token);

            Log::info('Subscribed to FCM topic', [
                'member_id' => $member->id,
                'topic' => $request->topic,
            ]);

            return response()->json([
                'message' => 'Subscribed to topic successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to FCM topic', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()->id,
                'topic' => $request->topic,
            ]);

            return response()->json([
                'message' => 'Failed to subscribe to topic',
            ], 500);
        }
    }

    public function unsubscribeFromTopic(Request $request)
    {
        $request->validate([
            'topic' => 'required|string',
        ]);

        try {
            $member = $request->user();
            if (!$member->fcm_token) {
                return response()->json([
                    'message' => 'FCM token not found',
                ], 400);
            }

            $this->fcmService->unsubscribeFromTopic($request->topic, $member->fcm_token);

            Log::info('Unsubscribed from FCM topic', [
                'member_id' => $member->id,
                'topic' => $request->topic,
            ]);

            return response()->json([
                'message' => 'Unsubscribed from topic successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe from FCM topic', [
                'error' => $e->getMessage(),
                'member_id' => $request->user()->id,
                'topic' => $request->topic,
            ]);

            return response()->json([
                'message' => 'Failed to unsubscribe from topic',
            ], 500);
        }
    }
} 