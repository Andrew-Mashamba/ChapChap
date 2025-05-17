<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MLMPointService;
use App\Services\MLMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionController extends Controller
{
    protected $pointService;
    protected $notificationService;

    public function __construct(
        MLMPointService $pointService,
        MLMNotificationService $notificationService
    ) {
        $this->pointService = $pointService;
        $this->notificationService = $notificationService;
    }

    /**
     * Calculate and distribute commissions for an order
     */
    public function calculateCommissions(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'member_id' => ['required', 'exists:users,id'],
        ]);

        try {
            DB::beginTransaction();

            $member = User::findOrFail($request->member_id);
            $amount = $request->amount;

            // Calculate personal commission (5%)
            $personalCommission = $amount * 0.05;
            $this->pointService->addPoints($member->id, $personalCommission);

            // Send personal commission notification
            $this->notificationService->sendCommissionAlert(
                $member->id,
                $personalCommission,
                $request->order_id
            );

            // Calculate and distribute team commissions
            $this->calculateTeamCommissions($member, $amount);

            DB::commit();

            return response()->json([
                'message' => 'Commissions calculated and distributed successfully',
                'personal_commission' => $personalCommission,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to calculate commissions: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to calculate commissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate team commissions for up to 4 levels
     */
    protected function calculateTeamCommissions(User $member, float $amount)
    {
        $upline = $member->upline;
        $level = 1;
        $commissionRates = [0.03, 0.02, 0.01, 0.005]; // 3%, 2%, 1%, 0.5%

        while ($upline && $level <= 4) {
            $commission = $amount * $commissionRates[$level - 1];
            $this->pointService->addPoints($upline->id, $commission);

            // Send team commission notification
            $this->notificationService->sendCommissionAlert(
                $upline->id,
                $commission,
                null,
                $level
            );

            $upline = $upline->upline;
            $level++;
        }
    }

    /**
     * Get member's commission history
     */
    public function getCommissionHistory(Request $request)
    {
        try {
            $user = $request->user();
            
            $history = DB::table('point_transactions')
                ->where('user_id', $user->id)
                ->where('type', 'commission')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'commission_history' => $history
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get commission history: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get commission history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 