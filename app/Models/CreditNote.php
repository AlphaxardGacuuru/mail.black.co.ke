<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (CreditNote $creditNote) {
            $creditNote->number = (static::max('number') ?? 0) + 1;
        });
    }

    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'CN-'.str_pad($value, 4, '0', STR_PAD_LEFT),
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    /*
     * Relationships
     */

    public function userUnit()
    {
        return $this->belongsTo(UserUnit::class);
    }
}
