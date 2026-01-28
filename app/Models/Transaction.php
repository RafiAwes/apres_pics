<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'stripe_payment_id',
        'amount',
        'currency',
        'type',
        'status',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
