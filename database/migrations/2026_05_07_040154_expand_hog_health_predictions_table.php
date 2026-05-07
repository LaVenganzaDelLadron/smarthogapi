<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Intentionally left blank.
// This migration originally expanded `hog_health_predictions`, but that table/model
// has been removed. Keeping an empty migration avoids failures during `migrate:fresh`.

return new class extends Migration
{
    public function up(): void
    {
        // no-op
    }

    public function down(): void
    {
        // no-op
    }
};

