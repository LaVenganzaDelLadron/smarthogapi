<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PredictWeightGrowth extends Command
{
    protected $signature = 'predict:weight-growth {--all : Predict for all hogs} {--hog-id= : Predict specific hog by ID}';

    protected $description = 'Run weight growth predictions via ML service';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $hogId = $this->option('hog-id');

        if ($all) {
            $this->info('Running weight growth forecasts for all hogs...');
            $result = $this->predictAllHogs();

            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($hogId) {
            $this->info("Predicting weight growth for hog {$hogId}...");
            $result = $this->predictionService->predictWeightGrowth((int) $hogId);

            $this->displaySingleResult($result, $hogId);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Specify either --all or --hog-id=ID');

        return self::INVALID;
    }

    private function predictAllHogs(): array
    {
        $hogCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        try {
            $hogs = DB::table('hogs')->pluck('id');

            foreach ($hogs as $hogId) {
                $hogCount++;

                try {
                    $result = $this->predictionService->predictWeightGrowth($hogId);

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $errors[] = "Hog {$hogId}: {$result['error']}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Hog {$hogId}: {$e->getMessage()}";
                }
            }

            return [
                'success' => true,
                'total_hogs' => $hogCount,
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
            $this->info('✓ Weight growth forecast completed');
            $this->line("Total hogs: {$result['total_hogs']}");
            $this->line("Successful: {$result['successful_predictions']}");
            $this->line("Failed: {$result['failed_predictions']}");

            if (! empty($result['errors'])) {
                $this->warn("\nErrors:");
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
        } else {
            $this->error("✗ Weight growth forecast failed: {$result['error']}");
        }
    }

    private function displaySingleResult(array $result, string $hogId): void
    {
        if ($result['success']) {
            $data = $result['data'];
            $cached = $result['cached'] ? '(cached)' : '';
            $this->info("✓ Forecast successful {$cached}");
            $this->line("Current: {$data['current_weight']}kg");
            $this->line("Next week: {$data['next_week_weight']}kg");
            $this->line("Next month: {$data['next_month_weight']}kg");
            $this->line("Daily growth: {$data['growth_rate_daily']}kg/day");
        } else {
            $this->error("✗ Forecast failed: {$result['error']}");
        }
    }
}
