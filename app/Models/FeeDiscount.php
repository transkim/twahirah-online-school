<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeDiscount extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['ends_on' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', now(config('school.timezone'))->toDateString()));
    }
}
