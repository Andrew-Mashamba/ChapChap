<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MLMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    protected $notificationService;

    public function __construct(MLMNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Register a new member
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'upline_id' => ['required', 'exists:users,id'],
        ]);

        try {
            DB::beginTransaction();

            // Create new member
            $member = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'upline_id' => $request->upline_id,
                'points' => 0,
                'team_points' => 0,
            ]);

            // Send notifications to uplines
            $this->notificationService->sendDownlineRegistrationAlert(
                $member->id,
                $member->name
            );

            DB::commit();

            return response()->json([
                'message' => 'Member registered successfully',
                'member' => $member
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to register member: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to register member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update member's FCM token
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        try {
            $user = $request->user();
            $user->fcm_token = $request->fcm_token;
            $user->save();

            return response()->json([
                'message' => 'FCM token updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update FCM token: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update FCM token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member's team structure
     */
    public function getTeamStructure(Request $request)
    {
        try {
            $user = $request->user();
            
            $teamStructure = [
                'member' => $user,
                'direct_downlines' => $user->downlines()->with('downlines')->get(),
                'upline' => $user->upline,
            ];

            return response()->json([
                'team_structure' => $teamStructure
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get team structure: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get team structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 