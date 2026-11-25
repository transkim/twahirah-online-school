<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('role')->default('student')->index()->after('username'); // admin, finance, teacher, student
            $table->string('phone')->nullable()->after('email');
            $table->string('student_number')->nullable()->unique();
            $table->string('timezone')->default('Africa/Kampala');
            $table->string('country')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->index(); // id in the old portal
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('mode'); // chat or zoom
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('course_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->timestamps();
        });

        // Which teacher can teach which course (and optionally only some levels).
        Schema::create('teacher_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'course_id', 'course_level_id']);
        });

        // Weekly hours a teacher is free to teach, in the teacher's own time zone.
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday ... 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('days_per_week')->nullable(); // Zoom: 2, 3 or 5
            $table->string('status')->default('active'); // active, paused, completed, cancelled
            $table->date('started_on');
            $table->timestamps();
            $table->unique(['student_id', 'course_id']);
        });

        // Chat groups replace WhatsApp groups.
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind'); // class, direct, custom
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('chat_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // teacher, member, admin
            $table->boolean('muted')->default(false);
            $table->timestamps();
            $table->unique(['chat_group_id', 'user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('text'); // text, voice, image, file, recitation
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Subscription plans set by the school (prices in whole UGX).
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode'); // chat or zoom
            $table->unsignedTinyInteger('days_per_week')->nullable(); // Zoom tiers: 2, 3, 5
            $table->string('cycle'); // day, week, month, year_12m, year_52w
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('sessions')->nullable(); // Zoom sessions included; null = unlimited
            $table->unsignedInteger('price_ugx');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['mode', 'days_per_week', 'cycle']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_ugx');
            $table->string('method'); // mtn, airtel, card, cash, manual
            $table->string('phone')->nullable();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode'); // copied from plan
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('sessions_total')->nullable();
            $table->unsignedSmallInteger('sessions_used')->default(0);
            $table->string('status')->default('active'); // active, ended, cancelled
            $table->string('note')->nullable(); // e.g. scholarship
            $table->timestamps();
            $table->index(['student_id', 'mode', 'ends_at']);
        });

        // Live Zoom classes, one to one or small group.
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind'); // one_to_one, group
            $table->unsignedTinyInteger('capacity');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('zoom_meeting_id')->nullable()->index();
            $table->text('join_url')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, live, done, cancelled
            $table->timestamps();
        });

        Schema::create('class_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->unsignedInteger('minutes_attended')->default(0);
            $table->boolean('session_deducted')->default(false);
            $table->string('status')->default('booked'); // booked, cancelled, attended, missed
            $table->timestamps();
            $table->unique(['class_session_id', 'student_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('present'); // present, absent
            $table->string('source'); // login, zoom, manual
            $table->timestamp('marked_at');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['enrolment_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['attendances', 'class_bookings', 'class_sessions', 'subscriptions', 'payments', 'plans',
            'chat_messages', 'chat_group_members', 'chat_groups', 'enrolments', 'teacher_availabilities',
            'teacher_courses', 'course_levels', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'phone', 'student_number', 'timezone', 'country', 'active', 'legacy_id']);
        });
    }
};
