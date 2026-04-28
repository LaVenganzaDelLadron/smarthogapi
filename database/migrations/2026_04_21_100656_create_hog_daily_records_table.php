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
        Schema::create('hog_daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hog_id')->index();
            $table->foreignId('hog_pen_id')->index();
            $table->decimal('weight');
            $table->decimal('feed_consumed');
            $table->string('health_status');
            $table->decimal('temperature', 8, 2);
            $table->string('activity_level');
            $table->string('notes');
            $table->dateTime('recorded_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hog_daily_records');
    }
};
