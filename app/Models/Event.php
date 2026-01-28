<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'date',
        'address',
        'is_active',
    ];

    protected $casts = [
        'date' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationship: Who created the event
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship: Users who paid for access (Many-to-Many via pivot)
    public function participants()
    {
        return $this->belongsToMany(User::class, 'event_access', 'event_id', 'user_id')
            ->withTimestamps();
    }
}
