<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_group_members', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->foreignId('forwarded_from_id')->nullable()->after('reply_to_id')->constrained('chat_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forwarded_from_id');
            $table->dropColumn(['attachment_name', 'attachment_mime']);
        });

        Schema::table('chat_group_members', function (Blueprint $table) {
            $table->dropColumn('last_read_at');
        });
    }
};
