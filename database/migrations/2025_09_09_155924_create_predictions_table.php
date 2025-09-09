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
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained('prediction_questions')->onDelete('cascade');
            $table->enum('choice', ['A', 'B']);
            $table->integer('bet_amount');
            $table->integer('potential_winnings');
            $table->integer('actual_winnings')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('multiplier_applied', 3, 2)->default(1.00);
            $table->timestamps();
            
            // Ensure one prediction per user per question
            $table->unique(['user_id', 'question_id']);
            
            // Add indexes for performance
            $table->index('user_id');
            $table->index('question_id');
            $table->index(['question_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
