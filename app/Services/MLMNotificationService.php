<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class MLMNotificationService
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    /**
     * Send commission alert notification
     */
    public function sendCommissionAlert(int $userId, float $amount, ?int $orderId = null, ?int $level = null)
    {
        try {
            $user = User::findOrFail($userId);
            if (!$user->fcm_token) return;

            $title = $level ? "Team Commission Earned" : "Commission Earned";
            $body = $level 
                ? "You earned \${$amount} from level {$level} team commission"
                : "You earned \${$amount} from your personal commission";

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'commission',
                    'amount' => (string)$amount,
                    'order_id' => (string)$orderId,
                    'level' => (string)$level,
                ]);

            $this->messaging->send($message);

            // Store notification in database
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => 'commission',
                'data' => json_encode([
                    'amount' => $amount,
                    'order_id' => $orderId,
                    'level' => $level,
                ]),
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send commission alert: " . $e->getMessage());
        }
    }

    /**
     * Send downline registration alert
     */
    public function sendDownlineRegistrationAlert(int $downlineId, string $downlineName)
    {
        try {
            $downline = User::findOrFail($downlineId);
            $upline = $downline->upline;

            if (!$upline || !$upline->fcm_token) return;

            $title = "New Team Member";
            $body = "{$downlineName} has joined your team!";

            $message = CloudMessage::withTarget('token', $upline->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'downline',
                    'downline_id' => (string)$downlineId,
                    'downline_name' => $downlineName,
                ]);

            $this->messaging->send($message);

            // Store notification in database
            DB::table('notifications')->insert([
                'user_id' => $upline->id,
                'title' => $title,
                'body' => $body,
                'type' => 'downline',
                'data' => json_encode([
                    'downline_id' => $downlineId,
                    'downline_name' => $downlineName,
                ]),
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send downline registration alert: " . $e->getMessage());
        }
    }

    /**
     * Send team milestone alert
     */
    public function sendTeamMilestoneAlert(int $userId, string $milestone, float $points)
    {
        try {
            $user = User::findOrFail($userId);
            if (!$user->fcm_token) return;

            $title = "Team Milestone Achieved";
            $body = "Your team has reached {$milestone} with {$points} points!";

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'milestone',
                    'milestone' => $milestone,
                    'points' => (string)$points,
                ]);

            $this->messaging->send($message);

            // Store notification in database
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => 'milestone',
                'data' => json_encode([
                    'milestone' => $milestone,
                    'points' => $points,
                ]),
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send team milestone alert: " . $e->getMessage());
        }
    }

    /**
     * Send sales target alert
     */
    public function sendSalesTargetAlert(int $userId, float $target, float $current)
    {
        try {
            $user = User::findOrFail($userId);
            if (!$user->fcm_token) return;

            $title = "Sales Target Update";
            $body = "You've reached {$current} points out of {$target} target points!";

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'sales_target',
                    'target' => (string)$target,
                    'current' => (string)$current,
                ]);

            $this->messaging->send($message);

            // Store notification in database
            DB::table('notifications')->insert([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => 'sales_target',
                'data' => json_encode([
                    'target' => $target,
                    'current' => $current,
                ]),
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send sales target alert: " . $e->getMessage());
        }
    }
} 