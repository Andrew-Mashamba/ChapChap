<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthSyncController extends Controller
{
    public function sync(Request $request)
    {

//dd($request);
        Log::info('Starting member sync process.', ['request' => $request->all()]);

        // Step 1: Validate incoming request
        $validator = Validator::make($request->all(), [
            'firebase_uid' => 'required|string|unique:members,firebase_uid',
            'phone_number' => 'required|string|unique:members,phone_number',
            'first_name' => 'required|string|max:255',
		'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:members,email',
            'referred_by_code' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for member sync.', ['errors' => $validator->errors()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Step 2: Prepare member data
        $memberData = [
            'uuid' => (string) Str::uuid(),
            'firebase_uid' => $request->firebase_uid,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
            'first_name' => $request->first_name,
	    'last_name' => $request->last_name,
            'referral_code' => strtoupper(Str::random(8)),
            'referred_by_code' => $request->referred_by_code,
            'joined_via_invite' => !empty($request->referred_by_code),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Log::info('Prepared member data for insertion.', ['data' => $memberData]);

        try {
            // Step 3: Insert into database
            $memberId = DB::table('members')->insertGetId($memberData);

            // Step 4: Retrieve and return the new member
            $newMember = DB::table('members')->where('id', $memberId)->first();

            Log::info('Member synced successfully.', ['member_id' => $memberId]);

            return response()->json([
                'status' => 'success',
                'message' => 'Member synced successfully.',
                'data' => [
                    'member' => $newMember
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Exception occurred during member sync.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while syncing the member.',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function login(Request $request)
    {

        $request->validate([
            'phone_number' => 'required|string',
            'pin' => 'required|string|min:4|max:6',
        ]);

        $member = DB::table('members')
            ->where('phone_number', $request->phone_number)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found',
            ], 404);
        }

        if (trim($member->pin) !== trim($request->pin)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate new token
        $token = Str::random(60);
        DB::table('members')->where('id', $member->id)->update([
            'api_token' => $token,
            'last_login_at' => now()
        ]);

        // Convert to array and exclude pin/otp if needed
        $memberData = collect((array) $member)
            ->except(['pin', 'otp']) // explicitly exclude sensitive fields
            ->toArray();

        // Inject new token
        $memberData['api_token'] = $token;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'requires_otp' => false, // or true if needed
            'data' => [
                'member' => $memberData,
            ],
        ]);

     }


}
