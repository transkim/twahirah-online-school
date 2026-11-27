<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The school's accounts: income and spending, each with optional sub-accounts.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // income, expense
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code')->nullable()->unique(); // fixed accounts the system posts to, e.g. subscriptions
            $table->timestamps();
        });

        $now = now();
        $insert = function (string $name, string $type, ?string $code = null, ?int $parent = null) use ($now): int {
            return DB::table('accounts')->insertGetId(['name' => $name, 'type' => $type, 'code' => $code, 'parent_id' => $parent, 'created_at' => $now, 'updated_at' => $now]);
        };
        $insert('Subscriptions', 'income', 'subscriptions');
        $insert('Registration fees', 'income', 'registration_fees');
        $insert('Certificate fees', 'income', 'certificate_fees');
        $insert('Old portal balances collected', 'income', 'old_balances');
        $insert('Donations', 'income');
        $daawahIn = $insert('Daawah', 'income', 'daawah_income');
        $insert('Daawah donations', 'income', null, $daawahIn);
        $insert('Teacher pay', 'expense');
        $insert('Internet, Zoom and SMS', 'expense');
        $insert('Rent and utilities', 'expense');
        $daawahOut = $insert('Daawah', 'expense', 'daawah_expense');
        $insert('Daawah programmes', 'expense', null, $daawahOut);
        $insert('Other spending', 'expense');

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->unsignedBigInteger('amount_ugx');
            $table->string('description');
            $table->date('entry_date');
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('entry_date');
        });

        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->string('title');
            $table->text('details')->nullable();
            $table->unsignedBigInteger('amount_ugx');
            $table->string('status')->default('pending'); // pending, approved, declined, paid
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // A discount for one student, taken off every plan they pay for until it ends.
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->string('reason');
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title')->nullable();
            $table->boolean('self_registered')->default(false);
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('work_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->text('plan');
            $table->text('done')->nullable();
            $table->text('office_comment')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'week_start']);
        });

        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('period'); // e.g. 2026 Term 4
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('strengths')->nullable();
            $table->text('to_improve')->nullable();
            $table->foreignId('appraised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_level_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('level_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_level_id')->nullable()->constrained('course_levels')->nullOnDelete();
            $table->foreignId('to_level_id')->nullable()->constrained('course_levels')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['level_changes', 'topics', 'feedback', 'appraisals', 'work_plans'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['job_title', 'self_registered', 'approved_at']);
        });
        foreach (['departments', 'fee_discounts', 'requisitions', 'ledger_entries', 'accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
