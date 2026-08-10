<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'gender', 'google_id', 'avatar', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'object',
        'email_verified_at' => 'datetime',
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y',
    ];

    protected string $guard_name = 'web';

    /**
     * Get the name of the guard associated with the user model.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
    }

    protected static function booted(): void
    {
        static::creating(function(User $user) {
            if ($user->phone) {
                $normalized = substr_replace($user->phone, '254', 0, -9);
                $user->hashed_phone = hash('sha256', $normalized);
            }
        });
    }

    /**
     * Accesors.
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => preg_match("/https/", $value) ? $value : "/storage/" . $value
        );
    }

    protected function emailVerifiedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('d M Y') : null,
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
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /*
     * Relationships
     */

    public function userSubscriptionPlans()
    {
        return $this->hasMany(UserSubscriptionPlan::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function property()
    {
        return $this->belongsToMany(Property::class, "user_properties");
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'user_units');
    }

    public function userProperties()
    {
        return $this->hasMany(UserProperty::class);
    }

    public function userUnits()
    {
        return $this->hasMany(UserUnit::class);
    }

    public function referralPayouts()
    {
        return $this->hasMany(ReferralPayout::class);
    }

    /*
     * Custom functions
     */

    public function activeSubscription()
    {
        $userSubscriptionPlan = $this->userSubscriptionPlans()
            ->where('status', 'active')
            ->first();

        if (! $userSubscriptionPlan) {
            return null;
        }

        $userSubscriptionPlan->name = $userSubscriptionPlan->subscriptionPlan->name;
        $userSubscriptionPlan->price = $userSubscriptionPlan->subscriptionPlan->price;
        $userSubscriptionPlan->max_units = $userSubscriptionPlan->subscriptionPlan->max_units;

        return $userSubscriptionPlan;
    }

    public function subscriptionByPropertyIds()
    {
        return $this->userProperties
            ->map(function($userProperty) {
                $activeSubscription = $userProperty
                    ->property
                    ->user
                    ->activeSubscription();

                return $activeSubscription ? $userProperty->property_id : null;
            })->filter();
    }

    public function currentUserUnit()
    {
        return $this->userUnits()
            ->whereNull("vacated_at")
            ->orderBy("id", "DESC")
            ->first();
    }

    public function currentUnit()
    {
        return $this->currentUserUnit()?->unit;
    }

    /**
     * Route notifications for the Vonage channel.
     *
     * @return string
     */
    public function routeNotificationForVonage()
    {
        return $this->phone;
    }
}
