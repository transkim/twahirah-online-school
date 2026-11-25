<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function bookings(): HasMany { return $this->hasMany(ClassBooking::class); }

    public function seatsLeft(): int
    {
        return $this->capacity - $this->bookings()->where('status', '!=', 'cancelled')->count();
    }
}
