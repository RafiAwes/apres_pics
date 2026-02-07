<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'event_id',
        'name',
        'email',
        'otp_code',
        'otp_expires_at',
        'access_token',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
