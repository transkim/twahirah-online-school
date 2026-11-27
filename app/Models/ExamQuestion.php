<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['options' => 'array'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
