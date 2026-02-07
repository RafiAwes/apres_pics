<?php

namespace App\Services;

use Illuminate\Support\Str;

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
}