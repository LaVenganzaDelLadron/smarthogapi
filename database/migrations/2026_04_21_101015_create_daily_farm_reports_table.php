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
        Schema::create('daily_farm_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->index();
            $table->decimal('total_feed_consumed', 8, 2);
            $table->integer('total_hogs');
            $table->decimal('avg_weight', 8, 2);
            $table->decimal('mortality_count');
            $table->dateTime('report_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_farm_reports');
    }
};
