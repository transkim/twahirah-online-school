<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations with the AI learning helper, one per student and lesson (or a general one).
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role'); // user, assistant
            $table->text('text'); // what is shown on the page
            $table->json('content')->nullable(); // the assistant's full reply, sent back unchanged on the next turn
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
