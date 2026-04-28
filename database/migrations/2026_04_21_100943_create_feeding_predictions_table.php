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
        Schema::create('feeding_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hog_pen_id')->index();
            $table->foreignId('ml_model_id')->index();
            $table->decimal('predicted_feed_amount', 8, 2);
            $table->decimal('confidence_score', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeding_predictions');
    }
};
