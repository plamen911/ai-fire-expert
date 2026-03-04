<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 36);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('message_index');
            $table->boolean('is_positive');
            $table->timestamps();

            $table->unique(['conversation_id', 'message_index', 'user_id'], 'feedback_unique');
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_feedback');
    }
};
