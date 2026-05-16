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
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->string('external_provider')->nullable()->after('api_provider');
            $table->string('external_device_id')->nullable()->after('external_provider');
            $table->json('external_metadata')->nullable()->after('external_device_id');

            $table->index(['external_provider', 'external_device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropIndex(['external_provider', 'external_device_id']);
            $table->dropColumn(['external_provider', 'external_device_id', 'external_metadata']);
        });
    }
};
