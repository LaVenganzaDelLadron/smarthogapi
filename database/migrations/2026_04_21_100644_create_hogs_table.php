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
        Schema::create('hogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hog_pen_id')->index();
            $table->string('ear_tag_id');
            $table->string('breed');
            $table->string('gender');
            $table->integer('current_age');
            $table->decimal('weight_current');
            $table->decimal('health_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hogs');
    }
};
