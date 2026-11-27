<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ExamAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'question_ids' => 'array', 'started_at' => 'datetime', 'due_at' => 'datetime',
        'submitted_at' => 'datetime', 'passed' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    /** The questions drawn for this attempt, in the order they were drawn. */
    public function questions(): Collection
    {
        $questions = ExamQuestion::whereIn('id', $this->question_ids)->get()->keyBy('id');

        return collect($this->question_ids)->map(fn (int $id) => $questions->get($id))->filter()->values();
    }
}
