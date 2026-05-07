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
        Schema::table('hog_health_predictions', function (Blueprint $table) {
            // Add columns for weight trend prediction
            $table->string('model_used')->nullable()->after('risk_score');
            $table->string('confidence_level')->nullable()->after('model_used');
            $table->text('confidence_reason')->nullable()->after('confidence_level');

            // Weight trend data from WeightResponse
            $table->json('weight_trend')->nullable()->after('confidence_reason');

            // Pen status prediction from PenStatusResponse
            $table->json('pen_status')->nullable()->after('weight_trend');

            // Warnings specific to this prediction
            $table->json('warnings')->nullable()->after('pen_status');

            // Model metrics and metadata
            $table->json('metrics')->nullable()->after('warnings');

            // Full FastAPI response for debugging/audit
            $table->json('fastapi_response')->nullable()->after('metrics');

            // When the prediction was generated
            $table->timestamp('predicted_at')->nullable()->after('fastapi_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hog_health_predictions', function (Blueprint $table) {
            $table->dropColumn([
                'model_used',
                'confidence_level',
                'confidence_reason',
                'weight_trend',
                'pen_status',
                'warnings',
                'metrics',
                'fastapi_response',
                'predicted_at',
            ]);
        });
    }
};
