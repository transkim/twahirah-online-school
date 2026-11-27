<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The lesson library: notes, audio, video and class recordings for a course (and optionally one level).
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('kind')->default('notes'); // notes, audio, video, recording
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->string('link_url')->nullable(); // e.g. a Zoom recording or YouTube link
            $table->timestamps();
            $table->index(['course_id', 'course_level_id']);
        });

        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->timestamp('submitted_at');
            $table->unsignedTinyInteger('mark')->nullable(); // out of 100
            $table->text('feedback')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
            $table->unique(['homework_id', 'student_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('everyone'); // everyone, students, teachers, course
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('sent_by_sms')->default(false);
            $table->unsignedInteger('recipients')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework');
        Schema::dropIfExists('lessons');
    }
};
