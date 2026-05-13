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
        Schema::create('prediction_cache', function (Blueprint $table) {
            $table->id();
            $table->string('prediction_type'); // feed_recommendation, weight_trend, pen_status
            $table->unsignedBigInteger('pen_id');
            $table->string('cache_key')->unique();
            $table->json('data');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['prediction_type', 'pen_id']);
            $table->index('expires_at');
            $table->foreign('pen_id')->references('id')->on('hog_pens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediction_cache');
    }
};
