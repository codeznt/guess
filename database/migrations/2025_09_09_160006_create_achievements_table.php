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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('achievement_type', 50);
            $table->string('title', 255);
            $table->text('description');
            $table->string('icon', 50);
            $table->integer('points_value')->default(0);
            $table->boolean('is_shareable')->default(true);
            $table->timestamp('earned_at');
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
            
            // Add indexes for performance
            $table->index('user_id');
            $table->index(['user_id', 'earned_at']);
            $table->index('achievement_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
