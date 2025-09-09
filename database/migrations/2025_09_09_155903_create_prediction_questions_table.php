<?php

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
        Schema::create('prediction_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('option_a', 255);
            $table->string('option_b', 255);
            $table->timestamp('resolution_time');
            $table->text('resolution_criteria');
            $table->enum('correct_answer', ['A', 'B'])->nullable();
            $table->enum('status', ['pending', 'active', 'resolved', 'cancelled'])->default('pending');
            $table->string('external_reference', 255)->nullable();
            $table->timestamps();
            
            // Add indexes for performance
            $table->index('category_id');
            $table->index('status');
            $table->index('resolution_time');
            $table->index(['status', 'resolution_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediction_questions');
    }
};
