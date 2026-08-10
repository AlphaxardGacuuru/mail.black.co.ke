<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service',      // e.g., 'kopokopo'
        'event_type',   // e.g., 'buygoods_transaction_received'
        'external_id',  // The ID returned by the K2 SDK
        'url',          // The endpoint URL you registered
        'status',       // 'active', 'inactive', 'pending'
        'meta',         // Full JSON response for debugging/auditing
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'json', // Laravel 9 automatically handles the JSON string/array conversion
    ];
}
