<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'limit_snapshot' => 'object',
        'due_date' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Loan $loan) {
            $loan->number = (static::max('number') ?? 0) + 1;
        });
    }

    /**
     * Accessors
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'L-'.str_pad($value, 4, '0', STR_PAD_LEFT),
        );
    }

    protected function dueDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
