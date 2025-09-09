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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->unique()->nullable()->after('id');
            $table->string('username')->nullable()->change();
            $table->string('first_name')->nullable()->after('username');
            $table->string('last_name')->nullable()->after('first_name');
            $table->integer('daily_coins')->default(1000)->after('last_name');
            $table->integer('total_predictions')->default(0)->after('daily_coins');
            $table->integer('correct_predictions')->default(0)->after('total_predictions');
            $table->integer('current_streak')->default(0)->after('correct_predictions');
            $table->integer('best_streak')->default(0)->after('current_streak');
            $table->date('last_active_date')->nullable()->after('best_streak');
            
            // Make email nullable for Telegram users
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            
            // Add indexes for performance
            $table->index('telegram_id');
            $table->index('last_active_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telegram_id']);
            $table->dropIndex(['last_active_date']);
            $table->dropColumn([
                'telegram_id',
                'first_name',
                'last_name',
                'daily_coins',
                'total_predictions',
                'correct_predictions',
                'current_streak',
                'best_streak',
                'last_active_date'
            ]);
            
            // Revert email and password to not nullable
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->string('username')->nullable(false)->change();
        });
    }
};
