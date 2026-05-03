<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PredictFeedDemand extends Command
{
    protected $signature = 'predict:feed-demand {--all : Predict for all farms} {--farm-id= : Predict specific farm by ID}';

    protected $description = 'Run feed demand predictions via ML service';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $farmId = $this->option('farm-id');

        if ($all) {
            $this->info('Running feed demand forecasts for all farms...');
            $result = $this->predictAllFarms();

            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($farmId) {
            $this->info("Predicting feed demand for farm {$farmId}...");
            $result = $this->predictionService->predictFeedDemand((int) $farmId);

            $this->displaySingleResult($result, $farmId);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Specify either --all or --farm-id=ID');

        return self::INVALID;
    }

    private function predictAllFarms(): array
    {
        $farmCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        try {
            $farms = DB::table('farms')->pluck('id');

            foreach ($farms as $farmId) {
                $farmCount++;

                try {
                    $result = $this->predictionService->predictFeedDemand($farmId);

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $errors[] = "Farm {$farmId}: {$result['error']}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Farm {$farmId}: {$e->getMessage()}";
                }
            }

            return [
                'success' => true,
                'total_farms' => $farmCount,
                'successful_predictions' => $successCount,
                'failed_predictions' => $failureCount,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function displayResult(array $result): void
    {
        if ($result['success']) {
            $this->info('✓ Feed demand forecast completed');
            $this->line("Total farms: {$result['total_farms']}");
            $this->line("Successful: {$result['successful_predictions']}");
            $this->line("Failed: {$result['failed_predictions']}");

            if (! empty($result['errors'])) {
                $this->warn("\nErrors:");
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
        } else {
            $this->error("✗ Feed demand forecast failed: {$result['error']}");
        }
    }

    private function displaySingleResult(array $result, string $farmId): void
    {
        if ($result['success']) {
            $data = $result['data'];
            $cached = $result['cached'] ? '(cached)' : '';
            $this->info("✓ Forecast successful {$cached}");
            $this->line("Tomorrow: {$data['tomorrow_feed_kg']}kg");
            $this->line("Weekly: {$data['weekly_feed_kg']}kg");
            $this->line("Confidence: {$data['forecast_confidence']}%");
        } else {
            $this->error("✗ Forecast failed: {$result['error']}");
        }
    }
}
