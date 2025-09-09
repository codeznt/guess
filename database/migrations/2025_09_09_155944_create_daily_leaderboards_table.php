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
        Schema::create('daily_leaderboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('leaderboard_date');
            $table->integer('total_winnings')->default(0);
            $table->integer('predictions_made')->default(0);
            $table->integer('correct_predictions')->default(0);
            $table->decimal('accuracy_percentage', 5, 2)->default(0);
            $table->integer('rank');
            $table->timestamps();
            
            // Ensure one entry per user per day
            $table->unique(['user_id', 'leaderboard_date']);
            
            // Add indexes for performance
            $table->index('leaderboard_date');
            $table->index(['leaderboard_date', 'rank']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_leaderboards');
    }
};
