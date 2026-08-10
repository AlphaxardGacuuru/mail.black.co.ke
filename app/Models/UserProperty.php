<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class UserProperty extends Model
{
    use HasFactory, HasRoles, HasUuids;

    protected string $guard_name = 'web';

    /**
     * Get the name of the guard associated with the user model.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /*
    * Custom Methods
    */

    public function can(string $permission): bool
    {
        return $this
            ->user
            ->getAllPermissions()
            ->pluck('name')
            ->contains($permission);
    }
}
