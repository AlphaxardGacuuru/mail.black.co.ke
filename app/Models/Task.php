<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y',
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->number = (static::max('number') ?? 0) + 1;
        });
    }

    /**
     * Accessors.
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'T-'.str_pad($value, 4, '0', STR_PAD_LEFT),
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function assignedTo()
    {
        return $this->belongsTo(UserProperty::class, 'assigned_to');
    }

    public function taskStages()
    {
        return $this->hasMany(TaskStage::class);
    }

    /*
     * Custom Functions
     */

    public function currentStage()
    {
        return $this->taskStages()
            ->orderBy("id", "desc")
            ->first();
    }
}
