<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_farm_reports', function (Blueprint $table) {
            $table->unsignedInteger('active_pens')->default(0)->after('mortality_count');
            $table->decimal('avg_temperature', 8, 2)->default(0)->after('active_pens');
            $table->decimal('avg_humidity', 8, 2)->default(0)->after('avg_temperature');
            $table->unsignedInteger('alerts_triggered')->default(0)->after('avg_humidity');
            $table->unsignedInteger('sick_hogs')->default(0)->after('alerts_triggered');
            $table->decimal('avg_weekly_weight_gain', 8, 2)->default(0)->after('sick_hogs');
            $table->unique(['farm_id', 'report_date'], 'daily_farm_reports_farm_id_report_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_farm_reports', function (Blueprint $table) {
            $table->dropUnique('daily_farm_reports_farm_id_report_date_unique');
            $table->dropColumn([
                'active_pens',
                'avg_temperature',
                'avg_humidity',
                'alerts_triggered',
                'sick_hogs',
                'avg_weekly_weight_gain',
            ]);
        });
    }
};
