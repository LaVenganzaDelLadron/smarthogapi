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
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->foreignId('farm_id')
                ->nullable()
                ->after('id')
                ->constrained('farms')
                ->cascadeOnDelete();

            $table->index(['farm_id', 'created_at']);
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('hog_pens', function (Blueprint $table) {
            $table->index(['farm_id', 'created_at']);
        });

        Schema::table('hogs', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeders', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_logs', function (Blueprint $table) {
            $table->index(['feeder_id', 'created_at']);
            $table->index(['pen_id', 'created_at']);
        });

        Schema::table('feeding_schedule', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_predictions', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_queue', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'status', 'scheduled_at']);
        });

        Schema::table('sensors', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->index(['sensor_id', 'created_at']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['farm_id', 'created_at']);
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('daily_farm_reports', function (Blueprint $table) {
            $table->index(['farm_id', 'report_date']);
        });

        Schema::table('iot_devices', function (Blueprint $table) {
            $table->index(['hog_pen_id', 'created_at']);
        });

        Schema::table('device_logs', function (Blueprint $table) {
            $table->index(['device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'created_at']);
        });

        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('daily_farm_reports', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'report_date']);
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'created_at']);
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropIndex(['sensor_id', 'created_at']);
        });

        Schema::table('sensors', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_queue', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'status', 'scheduled_at']);
        });

        Schema::table('feeding_predictions', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_schedule', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('feeding_logs', function (Blueprint $table) {
            $table->dropIndex(['feeder_id', 'created_at']);
            $table->dropIndex(['pen_id', 'created_at']);
        });

        Schema::table('feeders', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('hogs', function (Blueprint $table) {
            $table->dropIndex(['hog_pen_id', 'created_at']);
        });

        Schema::table('hog_pens', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'created_at']);
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'created_at']);
            $table->dropConstrainedForeignId('farm_id');
        });
    }
};
