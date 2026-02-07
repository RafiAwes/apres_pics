<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EventService
{
    public function generateSlug($title)
    {
        return Str::slug($title, '-');
    }

    public function generatePassword($length = 6)
    {
        return (string) random_int(000000, 999999);
    }

    public function setPasswordForEvent($event, $password)
    {
        $event->password = Hash::make($password) ?? null;
        $event->save();
        return $event;
    }
}