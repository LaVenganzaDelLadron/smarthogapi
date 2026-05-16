<?php

namespace App\Services;

use App\Models\Hogs;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    public function __construct(private FastAPIIntegration $fastapi) {}

    public function predictHogHealth(int $hogId): array
    {
        return $this->fastapi->predictHogHealth($hogId);
    }

    public function predictAllHogs(): array
    {
        $hogsCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        foreach (Hogs::query()->get(['id']) as $hog) {
            $hogsCount++;

            $result = $this->predictHogHealth((int) $hog->id);

            if ($result['success']) {
                $successCount++;

                continue;
            }

            $failureCount++;
            $errors[] = "Hog {$hog->id}: ".($result['error'] ?? $result['message'] ?? 'Prediction failed');
        }

        $summary = [
            'success' => true,
            'total_hogs' => $hogsCount,
            'successful_predictions' => $successCount,
            'failed_predictions' => $failureCount,
            'errors' => $errors,
        ];

        Log::info('Batch predictions completed', $summary);

        return $summary;
    }

    public function predictFeedDemand(int $farmId): array
    {
        return $this->fastapi->predictFeedDemand($farmId);
    }

    public function predictWeightGrowth(int $hogId): array
    {
        return $this->fastapi->predictWeightGrowth($hogId);
    }

    public function predictOutbreakRisk(int $penId): array
    {
        return $this->fastapi->predictOutbreakRisk($penId);
    }
}
