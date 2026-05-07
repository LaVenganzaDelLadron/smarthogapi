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
        Schema::table('feeding_predictions', function (Blueprint $table) {
            // Add missing columns from FastAPI FeedResponse
            $table->string('model_used')->nullable()->after('confidence_score');
            $table->string('confidence_level')->nullable()->after('model_used');
            $table->text('confidence_reason')->nullable()->after('confidence_level');

            // Feed recommendation details
            $table->json('feed_recommendation')->nullable()->after('confidence_reason');
            $table->json('feed_totals')->nullable()->after('feed_recommendation');

            // Weight trend (list of predicted rows)
            $table->json('weight_trend')->nullable()->after('feed_totals');

            // Pen status prediction details
            $table->json('pen_status')->nullable()->after('weight_trend');

            // Alerts, warnings, and suggestions
            $table->json('warnings')->nullable()->after('pen_status');
            $table->json('alerts')->nullable()->after('warnings');
            $table->json('suggestions')->nullable()->after('alerts');

            // Track the FastAPI response
            $table->json('fastapi_response')->nullable()->after('suggestions');
            $table->timestamp('predicted_at')->nullable()->after('fastapi_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeding_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'model_used',
                'confidence_level',
                'confidence_reason',
                'feed_recommendation',
                'feed_totals',
                'weight_trend',
                'pen_status',
                'warnings',
                'alerts',
                'suggestions',
                'fastapi_response',
                'predicted_at',
            ]);
        });
    }
};
