<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns for records brought over from the old school portal.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('phone');
            $table->date('joined_on')->nullable()->after('gender');
        });

        Schema::table('enrolments', function (Blueprint $table) {
            // Old portal fee balance for this course. Negative means the student owes, positive is credit.
            $table->integer('legacy_balance_ugx')->default(0);
            $table->date('last_seen_on')->nullable();
            $table->date('ended_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropColumn(['legacy_balance_ugx', 'last_seen_on', 'ended_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'joined_on']);
        });
    }
};
