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
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'gender', 'google_id', 'avatar', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, HasUuids, Notifiable, TwoFactorAuthentication;

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
        'active_mailgun_account_id',
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
        'notification_preferences' => 'array',
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'verified' => 'boolean',
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

    /*
     * Custom functions
     */

    /**
     * Route notifications for the Vonage channel.
     *
     * @return string
     */
    public function routeNotificationForVonage()
    {
        return $this->phone;
    }

    /**
     * Determine whether the user has their own Mailgun credentials configured.
     */
    public function hasMailgunCredentials(): bool
    {
        $domain = $this->activeMailgunAccount?->mailgunDomain;

        return $domain !== null
            && filled($domain->domain)
            && filled($domain->api_key);
    }

    /**
     * Each notification category's channels (in_app, push, email): what the
     * user saved, else the defaults in config/notifications.php, with any
     * channel that can't be used switched off.
     *
     * @return array<string, array<string, bool>>
     */
    public function notificationPreferences(): array
    {
        $saved = $this->notification_preferences ?? [];

        return collect(config('notifications.categories'))
            ->map(fn (array $category, string $key) => collect($category['defaults'])
                ->map(fn (bool $default, string $channel) => ! $this->notificationChannelBlocked($key, $channel)
                    && (bool) ($saved[$key][$channel] ?? $default))
                ->all())
            ->all();
    }

    public function wantsNotification(string $category, string $channel): bool
    {
        return $this->notificationPreferences()[$category][$channel] ?? false;
    }

    /**
     * Why a channel can't be used for a category, or null if it can.
     */
    public function notificationChannelBlocked(string $category, string $channel): ?string
    {
        // An email about new mail sent to a mailbox hosted here would itself
        // be new mail, and notify again, forever.
        if ($category === 'new_mail' && $channel === 'email' && $this->emailIsHostedMailbox()) {
            return 'Your login email is one of the mailboxes here, so emailing it about new mail would loop.';
        }

        return null;
    }

    protected function emailIsHostedMailbox(): bool
    {
        return once(fn () => MailgunAccount::query()
            ->whereRaw('lower(mailbox_address) = ?', [mb_strtolower((string) $this->email)])
            ->exists());
    }

    public function mailgunDomains()
    {
        return $this->hasMany(MailgunDomain::class);
    }

    public function mailgunAccounts()
    {
        return $this->hasMany(MailgunAccount::class);
    }

    public function activeMailgunAccount()
    {
        return $this->belongsTo(MailgunAccount::class, 'active_mailgun_account_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function jobSeekerProfile()
    {
        return $this->hasOne(JobSeekerProfile::class);
    }

    public function cvRevamp()
    {
        return $this->hasOne(CvRevamp::class);
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function companyCareerPages()
    {
        return $this->hasMany(CompanyCareerPage::class);
    }
}
