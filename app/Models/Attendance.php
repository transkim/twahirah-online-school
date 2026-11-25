<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $guarded = ['id'];

    protected $casts = ["marked_at" => "datetime"]; // "date" stays a plain Y-m-d string so look-ups by day match

    public function student(): BelongsTo { return $this->belongsTo(User::class, "student_id"); }
    public function enrolment(): BelongsTo { return $this->belongsTo(Enrolment::class); }
}
