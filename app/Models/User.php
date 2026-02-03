<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Laravel\Cashier\Billable;
use App\Traits\ApiResponseTraits;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable, ApiResponseTraits;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'contact_number',
        'address',
        'is_active',
        'stripe_customer_id',
        'google_id',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'gender',
        'ban_type',
        'ban_expires_at',
        'ban_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'ban_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAvatarAttribute($value)
    {
        if ($value) {
            return url($value);
        } else {
            return url('images/user/default.jpg');
        }
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function hasActiveSubscription()
    {
        $subscription = $this->subscription;

        if ($subscription && $subscription->status === 'active' && $subscription->ends_at >= now()->toDateString()) {
            return true;
        }

        return false;
    }

    public function hasEventAccess($eventId)
    {
        return DB::table('event_access')
            ->where('user_id', $this->id)
            ->where('event_id', $eventId)
            ->exists();
    }

    public function eventsCreated()
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    public function subscriptions()
    {
        return $this->hasOne(Subscription::class);
    }

    public function hasActiveMonthlySubscription()
    {
        // 'default' is the name we will give the subscription
        return $this->subscribed('default'); 
    }


}