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
        Schema::create('feeding_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feeder_id')->constrained('feeders')->onDelete('cascade');
            $table->foreignId('hog_pen_id')->constrained('hog_pens')->onDelete('cascade');
            $table->string('feed_type'); // "starter", "grower", "finisher"
            $table->timestamp('scheduled_at'); // When it should run
            $table->timestamp('actual_feed_time')->nullable(); // When relay actually activated
            $table->string('status')->default('pending'); // "pending", "processing", "completed", "skipped", "error"
            $table->unsignedInteger('duration_seconds')->default(30); // How long relay was on
            $table->decimal('amount_dispensed', 8, 2)->nullable(); // Weight in kg
            $table->text('error_message')->nullable(); // If failed
            $table->timestamps();
            $table->index(['feeder_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeding_queue');
    }
};
