<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatConversationParticipant extends Pivot
{
    public $incrementing = true;

    protected $casts = [
        'last_read_at' => 'datetime',
        'is_sharing_location' => 'boolean',
        'location_latitude' => 'float',
        'location_longitude' => 'float',
        'location_accuracy_meters' => 'float',
    ];

    // last_read_at is cast, so Eloquent re-parses and reformats it on every
    // write (including updateExistingPivot(), which still goes through the
    // pivot model's attribute mutators) — without this override that
    // reformat uses the query grammar's default second-precision format and
    // silently truncates the microseconds back off, even on a column that
    // stores them and even if the caller already passed a precise value.
    protected $dateFormat = 'Y-m-d H:i:s.u';
}
