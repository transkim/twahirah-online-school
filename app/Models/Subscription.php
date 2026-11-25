<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }

    /** Sessions left, or null when the plan has no session limit. */
    public function sessionsLeft(): ?int
    {
        return $this->sessions_total === null ? null : max(0, $this->sessions_total - $this->sessions_used);
    }

    /** Usable now: active, inside its dates, and (for session plans) sessions left. */
    public function isUsable(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        return $this->status === 'active'
            && $this->starts_at->lte($at)
            && $this->ends_at->gt($at)
            && ($this->sessions_total === null || $this->sessions_used < $this->sessions_total);
    }

    public function scopeUsable(Builder $q, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $q->where('status', 'active')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->where(fn ($w) => $w->whereNull('sessions_total')->orWhereColumn('sessions_used', '<', 'sessions_total'));
    }
}
