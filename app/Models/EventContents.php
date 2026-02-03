<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventContents extends Model
{
    protected $fillable = [
        'event_id',
        'image',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getImageAttribute($value)
    {
       if ($value)
        {
            return url($value);
        }
        else {
            return url('images/event/default.jpg');
        }
    }
}
