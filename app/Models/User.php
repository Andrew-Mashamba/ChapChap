<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'upline_id',
        'points',
        'team_points',
        'fcm_token'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'points' => 'integer',
        'team_points' => 'integer'
    ];

    public function upline()
    {
        return $this->belongsTo(User::class, 'upline_id');
    }

    public function downlines()
    {
        return $this->hasMany(User::class, 'upline_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->notifications()->where('read', false);
    }

    public function markNotificationAsRead($notificationId)
    {
        $notification = $this->notifications()->findOrFail($notificationId);
        $notification->update([
            'read' => true,
            'read_at' => now()
        ]);
    }

    public function markAllNotificationsAsRead()
    {
        $this->unreadNotifications()->update([
            'read' => true,
            'read_at' => now()
        ]);
    }
} 