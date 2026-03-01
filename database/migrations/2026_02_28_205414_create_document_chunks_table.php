<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPostgres = Schema::getConnection() instanceof PostgresConnection;

        if ($isPostgres) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('document_chunks', function (Blueprint $table) use ($isPostgres) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');

            if ($isPostgres) {
                $table->vector('embedding', dimensions: 1536)->index();
            } else {
                $table->text('embedding')->nullable();
            }

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
