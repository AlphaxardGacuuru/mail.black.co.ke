<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        "size" => "array",
        "service_charge" => "object",
    ];

    /**
     * Accesors.
     */
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

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(User::class, 'user_units');
    }

    public function userUnits()
    {
        return $this->hasMany(UserUnit::class);
    }

    /*
     * Custom Functions
     */

    public function currentUserUnit()
    {
        return $this->userUnits()
            ->whereNull("vacated_at")
            ->orderBy("id", "DESC")
            ->first();
    }

    public function currentTenant()
    {
        return $this->currentUserUnit()?->user;
    }
}
