<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'date',
        'address',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            $event->slug = self::generateSlug();
        });
    }

    /**
     * Generate a 10-character random slug that always starts with an alphabetical character.
     */
    public static function generateSlug()
    {
        $firstChar = chr(rand(97, 122)); // Random lowercase letter a-z
        $rest = \Illuminate\Support\Str::random(9);
        return $firstChar . $rest;
    }

    /**
     * Resolve the route binding for a given value.
     * Support lookup by numeric ID or alphanumeric Slug.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)
            ->orWhere('slug', $value)
            ->first();
    }

    /**
     * Static helper to find an event by ID or Slug.
     */
    public static function findByIdOrSlug($identifier)
    {
        return static::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    protected $casts = [
        'date' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? \Carbon\Carbon::parse($value)->format('F j, Y') : null,
        );
    }

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

    public function eventContents()
    {
        return $this->hasMany(EventContents::class);
    }
}
