<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassBooking extends Model
{
    protected $guarded = ['id'];

    protected $casts = ["joined_at" => "datetime", "left_at" => "datetime", "session_deducted" => "boolean"];

    public function classSession(): BelongsTo { return $this->belongsTo(ClassSession::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, "student_id"); }
}
