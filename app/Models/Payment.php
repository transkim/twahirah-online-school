<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ["paid_at" => "datetime"];

    public function student(): BelongsTo { return $this->belongsTo(User::class, "student_id"); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}
