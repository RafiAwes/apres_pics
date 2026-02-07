<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class EventService
{
    public function generateSlug($title)
    {
        return Str::slug($title, '-');
    }

    public function generatePassword($length = 6)
    {
        $num = random_int(0, 999999);
        return str_pad((string) $num, $length, '0', STR_PAD_LEFT);
    }

    public function setPasswordForEvent($event, $password)
    {
        $event->password = $password ? Crypt::encryptString($password) : null;
        $event->save();
        return $event;
    }

    public function decryptPassword($event)
    {
        if (! $event->password) {
            return null;
        }

        return Crypt::decryptString($event->password);
    }
}