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
        Schema::create('feeding_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hog_pen_id')->constrained()->onDelete('cascade');
            $table->dateTime('time'); // correct Laravel syntax
            $table->decimal('feed_amount', 8, 2);
            $table->string('feed_type')->nullable();
            // example: starter, grower, finisher
            $table->string('mode')->default('auto');
            // auto | manual | scheduled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeding_schedule');
    }
};
