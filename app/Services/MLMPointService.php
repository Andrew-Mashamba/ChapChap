<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLMPointService
{
    protected $notificationService;
    const PERSONAL_SALES_POINTS_THRESHOLD = 20; // 20,000 TZS in profit
    const TEAM_SALES_POINTS_THRESHOLD = 50; // 50,000 TZS in profit
    const POINT_VALUE = 1000; // 1 point = 1,000 TZS in profit

    public function __construct(MLMNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Calculate points from order profit
     */
    public function calculatePointsFromProfit($profit)
    {
        return floor($profit / self::POINT_VALUE);
    }

    /**
     * Update member's points and check for milestones
     */
    public function updateMemberPoints($memberId, $orderId)
    {
        try {
            DB::beginTransaction();

            $member = User::findOrFail($memberId);
            $order = Order::findOrFail($orderId);

            // Calculate profit from order
            $profit = $order->total_amount - $order->wholesale_amount;
            $points = $this->calculatePointsFromProfit($profit);

            // Update member's points
            $oldPoints = $member->points;
            $newPoints = $oldPoints + $points;
            $member->points = $newPoints;
            $member->save();

            // Check for personal sales milestone
            if ($oldPoints < self::PERSONAL_SALES_POINTS_THRESHOLD && 
                $newPoints >= self::PERSONAL_SALES_POINTS_THRESHOLD) {
                $this->notificationService->sendTeamMilestoneAlert(
                    $memberId,
                    'Personal Sales Qualification',
                    'You have reached 20 points and are now eligible for personal sales commission!'
                );
            }

            // Check for team sales milestone
            if ($oldPoints < self::TEAM_SALES_POINTS_THRESHOLD && 
                $newPoints >= self::TEAM_SALES_POINTS_THRESHOLD) {
                $this->notificationService->sendTeamMilestoneAlert(
                    $memberId,
                    'Team Sales Qualification',
                    'You have reached 50 points and are now eligible for team commission!'
                );
            }

            // Update team points
            $this->updateTeamPoints($member);

            DB::commit();
            Log::info("Points updated for member $memberId: +$points points");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update member points: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update team points for uplines
     */
    protected function updateTeamPoints(User $member)
    {
        $upline = $member->upline;
        $level = 1;

        while ($upline && $level <= 4) {
            $upline->team_points += $member->points;
            $upline->save();
            $upline = $upline->upline;
            $level++;
        }
    }

    /**
     * Check if member is eligible for personal commission
     */
    public function isEligibleForPersonalCommission($memberId)
    {
        $member = User::findOrFail($memberId);
        return $member->points >= self::PERSONAL_SALES_POINTS_THRESHOLD;
    }

    /**
     * Check if member is eligible for team commission
     */
    public function isEligibleForTeamCommission($memberId)
    {
        $member = User::findOrFail($memberId);
        return $member->team_points >= self::TEAM_SALES_POINTS_THRESHOLD;
    }

    /**
     * Add points to a member's account
     */
    public function addPoints(int $userId, float $points)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $user->points += $points;
            $user->save();

            // Record the transaction
            DB::table('point_transactions')->insert([
                'user_id' => $userId,
                'points' => $points,
                'type' => 'commission',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update team points for uplines
            $this->updateTeamPoints($user);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to add points: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get member's point balance
     */
    public function getPointBalance(int $userId): float
    {
        $user = User::findOrFail($userId);
        return $user->points;
    }

    /**
     * Get member's team points
     */
    public function getTeamPoints(int $userId): float
    {
        $user = User::findOrFail($userId);
        return $user->team_points;
    }
} 