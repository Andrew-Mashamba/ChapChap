<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Illuminate\Support\Facades\Log;

class FCMService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path('firebase-credentials.json'));

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send a notification to a specific device
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = [])
    {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            Log::info('FCM notification sent successfully', [
                'token' => $token,
                'title' => $title,
                'body' => $body,
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to send FCM notification', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            throw $e;
        }
    }

    /**
     * Send a notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [])
    {
        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            Log::info('FCM topic notification sent successfully', [
                'topic' => $topic,
                'title' => $title,
                'body' => $body,
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to send FCM topic notification', [
                'error' => $e->getMessage(),
                'topic' => $topic,
            ]);
            throw $e;
        }
    }

    /**
     * Subscribe a device token to a topic
     */
    public function subscribeToTopic(string $topic, string $token)
    {
        try {
            $this->messaging->subscribeToTopic($topic, [$token]);
            Log::info('Device subscribed to topic', [
                'topic' => $topic,
                'token' => $token,
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to subscribe device to topic', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'token' => $token,
            ]);
            throw $e;
        }
    }

    /**
     * Unsubscribe a device token from a topic
     */
    public function unsubscribeFromTopic(string $topic, string $token)
    {
        try {
            $this->messaging->unsubscribeFromTopic($topic, [$token]);
            Log::info('Device unsubscribed from topic', [
                'topic' => $topic,
                'token' => $token,
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to unsubscribe device from topic', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'token' => $token,
            ]);
            throw $e;
        }
    }

    /**
     * Send order status notification
     */
    public function sendOrderStatusNotification(string $token, string $orderId, string $status, ?string $note = null)
    {
        $title = 'Order Status Update';
        $body = "Your order #{$orderId} status has been updated to {$status}";
        if ($note) {
            $body .= ": {$note}";
        }

        $data = [
            'type' => 'order',
            'order_id' => $orderId,
            'status' => $status,
            'note' => $note,
        ];

        return $this->sendToDevice($token, $title, $body, $data);
    }

    /**
     * Send commission notification
     */
    public function sendCommissionNotification(string $token, float $amount, string $type)
    {
        $title = 'New Commission Earned';
        $body = "You have earned a commission of {$amount} for {$type}";

        $data = [
            'type' => 'commission',
            'amount' => $amount,
            'commission_type' => $type,
        ];

        return $this->sendToDevice($token, $title, $body, $data);
    }

    /**
     * Send downline activity notification
     */
    public function sendDownlineActivityNotification(string $token, string $downlineId, string $activity)
    {
        $title = 'Downline Activity';
        $body = "Your downline #{$downlineId} has {$activity}";

        $data = [
            'type' => 'downline',
            'downline_id' => $downlineId,
            'activity' => $activity,
        ];

        return $this->sendToDevice($token, $title, $body, $data);
    }

    /**
     * Send promotion notification
     */
    public function sendPromotionNotification(string $topic, string $title, string $description)
    {
        $body = $description;

        $data = [
            'type' => 'promotion',
            'title' => $title,
            'description' => $description,
        ];

        return $this->sendToTopic($topic, $title, $body, $data);
    }

    /**
     * Send inventory alert
     */
    public function sendInventoryAlert(string $token, string $productId, string $productName, int $quantity)
    {
        $title = 'Low Inventory Alert';
        $body = "Product {$productName} is running low on stock. Only {$quantity} items remaining.";

        $data = [
            'type' => 'inventory',
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $quantity,
        ];

        return $this->sendToDevice($token, $title, $body, $data);
    }

    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation(string $token, string $orderId, float $amount)
    {
        $title = 'Payment Confirmed';
        $body = "Your payment of {$amount} for order #{$orderId} has been confirmed.";

        $data = [
            'type' => 'payment',
            'order_id' => $orderId,
            'amount' => $amount,
        ];

        return $this->sendToDevice($token, $title, $body, $data);
    }
} 