<?php

namespace App\Observers;

use App\Jobs\AsyncPredictionJob;
use App\Models\Hogs;

class HogsObserver
{
    public function created(Hogs $hog): void
    {
        $webhookUrls = array_filter(explode(',', config('services.fastapi.webhooks', '')));

        // Auto-generate feed recommendation prediction
        AsyncPredictionJob::dispatch(
            predictionType: 'feed_recommendation',
            penId: $hog->hog_pen_id,
            options: [],
            webhookUrls: $webhookUrls
        );

        // Auto-generate weight trend prediction
        AsyncPredictionJob::dispatch(
            predictionType: 'weight_trend',
            penId: $hog->hog_pen_id,
            options: [],
            webhookUrls: $webhookUrls
        );

        // Auto-generate pen status prediction
        AsyncPredictionJob::dispatch(
            predictionType: 'pen_status',
            penId: $hog->hog_pen_id,
            options: [],
            webhookUrls: $webhookUrls
        );
    }
}
