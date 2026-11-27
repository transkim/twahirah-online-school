<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Online exams. Each exam has its own question bank; an attempt can draw a few questions at random.
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('minutes')->default(30);
            $table->unsignedTinyInteger('pass_mark')->default(50);
            $table->unsignedTinyInteger('max_attempts')->nullable(); // null = unlimited retakes
            $table->unsignedSmallInteger('questions_per_attempt')->nullable(); // null = every question
            $table->boolean('published')->default(false);
            $table->boolean('gives_certificate')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // choice, written
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->timestamps();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->json('question_ids');
            $table->timestamp('started_at');
            $table->timestamp('due_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('in_progress'); // in_progress, waiting_for_teacher, marked
            $table->unsignedInteger('points')->nullable();
            $table->unsignedInteger('max_points')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // percent
            $table->boolean('passed')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('choice')->nullable();
            $table->text('text')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedSmallInteger('points_awarded')->nullable();
            $table->timestamps();
            $table->unique(['exam_attempt_id', 'exam_question_id']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['student_id', 'exam_id']);
        });

        // Grade bands for marks and report cards, such as A for 80 and above.
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('min_score');
            $table->string('comment')->nullable();
            $table->timestamps();
        });

        DB::table('grade_bands')->insert(collect([
            ['A', 80, 'Excellent'], ['B', 70, 'Very good'], ['C', 60, 'Good'], ['D', 50, 'Fair'], ['F', 0, 'Needs more effort'],
        ])->map(fn ($b) => ['name' => $b[0], 'min_score' => $b[1], 'comment' => $b[2], 'created_at' => now(), 'updated_at' => now()])->all());

        // Every mark a student gets: from exams, homework or a teacher's own assessment.
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // exam, homework, assessment
            $table->string('title');
            $table->unsignedTinyInteger('score'); // percent
            $table->date('given_on');
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('homework_id')->nullable()->constrained('homework')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        foreach (['marks', 'grade_bands', 'certificates', 'exam_answers', 'exam_attempts', 'exam_questions', 'exams'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
