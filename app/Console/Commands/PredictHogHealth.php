<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;

class PredictHogHealth extends Command
{
    protected $signature = 'predict:hog-health {--all : Predict all hogs} {--hog-id= : Predict specific hog by ID}';

    protected $description = 'Run hog health predictions via ML service';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $hogId = $this->option('hog-id');

        if ($all) {
            $this->info('Running batch predictions for all hogs...');
            $result = $this->predictionService->predictAllHogs();

            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($hogId) {
            $this->info("Predicting health for hog {$hogId}...");
            $result = $this->predictionService->predictHogHealth((int) $hogId);

            $this->displaySingleResult($result, $hogId);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Specify either --all or --hog-id=ID');

        return self::INVALID;
    }

    private function displayResult(array $result): void
    {
        if ($result['success']) {
            $this->info('✓ Batch prediction completed');
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
            $this->error("✗ Batch prediction failed: {$result['error']}");
        }
    }

    private function displaySingleResult(array $result, string $hogId): void
    {
        if ($result['success']) {
            $data = $result['data'];
            $cached = $result['cached'] ? '(cached)' : '';
            $this->info("✓ Prediction successful {$cached}");
            $this->line("Status: {$data['predicted_status']}");
            $this->line("Risk Score: {$data['risk_score']}");
        } else {
            $this->error("✗ Prediction failed: {$result['error']}");
        }
    }
}
