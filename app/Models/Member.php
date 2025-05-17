<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'pin',
        'gender',
        'date_of_birth',
        'country',
        'region',
        'district',
        'ward',
        'street',
        'postal_code',
        'latitude',
        'longitude',
        'photo_path',
        'id_document_path',
        'shop_name',
        'shop_description',
        'shop_logo',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'mobile_money_number',
        'seller_id',
        'upline_id',
        'seller_level',
        'commission_balance',
        'total_sales_volume',
        'total_downlines',
        'account_status',
        'sponsor_id',
        'profile_image',
        'status',
        'seller_level',
        'commission_rate',
        'total_sales',
        'total_commission',
        'wallet_balance',
    ];

    protected $hidden = [
        'pin',
        'remember_token',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'commission_balance' => 'decimal:2',
        'total_sales_volume' => 'decimal:2',
        'total_downlines' => 'integer',
        'email_verified_at' => 'datetime',
        'seller_level' => 'integer',
        'commission_rate' => 'float',
        'total_sales' => 'float',
        'total_commission' => 'float',
        'wallet_balance' => 'float',
    ];

    // Mutators
    public function setPinAttribute($value)
    {
        $this->attributes['pin'] = Hash::make($value);
    }

    // Relationships
    public function upline()
    {
        return $this->belongsTo(Member::class, 'upline_id', 'seller_id');
    }

    public function downlines()
    {
        return $this->hasMany(Member::class, 'upline_id', 'seller_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function sponsor()
    {
        return $this->belongsTo(Member::class, 'sponsor_id');
    }

    // Accessors & Mutators
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getIsActiveAttribute()
    {
        return $this->account_status === 'active';
    }

    public function getProfileImageUrlAttribute()
    {
        return $this->profile_image
            ? asset('storage/' . $this->profile_image)
            : asset('images/default-profile.png');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('account_status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('account_status', '!=', 'active');
    }

    public function scopeBySellerLevel($query, $level)
    {
        return $query->where('seller_level', $level);
    }
}
