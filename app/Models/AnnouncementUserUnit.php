<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementUserUnit extends Model
{
    use HasFactory, HasUuids;

    /*
    * Relationships
    */

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function userUnit()
    {
        return $this->belongsTo(UserUnit::class);
    }
}
