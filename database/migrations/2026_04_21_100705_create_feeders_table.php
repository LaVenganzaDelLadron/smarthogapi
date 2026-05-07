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
        Schema::create('feeders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hog_pen_id')->index();
            $table->foreignId('device_id');
            $table->string('status');
            $table->timestamp('last_refill')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeders');
    }
};
