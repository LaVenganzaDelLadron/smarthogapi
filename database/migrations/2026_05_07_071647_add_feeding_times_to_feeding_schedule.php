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
        Schema::table('feeding_schedule', function (Blueprint $table) {
            // Store multiple feeding times as JSON array (e.g., ["06:00", "12:00", "18:00"])
            $table->json('feeding_times')->nullable()->after('time');

            // Track number of daily feedings for quick reference
            $table->tinyInteger('daily_feeding_count')->default(1)->after('feeding_times');

            // Index for queries filtering by feeding count
            $table->index('daily_feeding_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeding_schedule', function (Blueprint $table) {
            $table->dropIndex(['daily_feeding_count']);
            $table->dropColumn(['feeding_times', 'daily_feeding_count']);
        });
    }
};
