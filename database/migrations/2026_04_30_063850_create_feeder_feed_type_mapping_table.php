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
        Schema::create('feeder_feed_type_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feeder_id')->constrained('feeders')->onDelete('cascade');
            $table->string('feed_type'); // "starter", "grower", "finisher"
            $table->unsignedInteger('relay_pin')->nullable(); // GPIO pin number (e.g., 12, 14, 27, 26)
            $table->unsignedInteger('max_duration_seconds')->default(30); // Safety timeout
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['feeder_id', 'feed_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeder_feed_type_mapping');
    }
};
