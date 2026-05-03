<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PredictOutbreakRisk extends Command
{
    protected $signature = 'predict:outbreak-risk {--all : Assess all pens} {--pen-id= : Assess specific pen by ID}';

    protected $description = 'Run disease outbreak risk assessments via ML service';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $penId = $this->option('pen-id');

        if ($all) {
            $this->info('Running outbreak risk assessments for all pens...');
            $result = $this->assessAllPens();

            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($penId) {
            $this->info("Assessing outbreak risk for pen {$penId}...");
            $result = $this->predictionService->predictOutbreakRisk((int) $penId);

            $this->displaySingleResult($result, $penId);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Specify either --all or --pen-id=ID');

        return self::INVALID;
    }

    private function assessAllPens(): array
    {
        $penCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $highRiskCount = 0;
        $errors = [];

        try {
            $pens = DB::table('hog_pens')->pluck('id');

            foreach ($pens as $penId) {
                $penCount++;

                try {
                    $result = $this->predictionService->predictOutbreakRisk($penId);

                    if ($result['success']) {
                        $successCount++;
                        if (($result['data']['risk_level'] ?? 'LOW') === 'HIGH') {
                            $highRiskCount++;
                        }
                    } else {
                        $failureCount++;
                        $errors[] = "Pen {$penId}: {$result['error']}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Pen {$penId}: {$e->getMessage()}";
                }
            }

            return [
                'success' => true,
                'total_pens' => $penCount,
                'successful_assessments' => $successCount,
                'failed_assessments' => $failureCount,
                'high_risk_pens' => $highRiskCount,
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
            $this->info('✓ Outbreak risk assessment completed');
            $this->line("Total pens: {$result['total_pens']}");
            $this->line("Successful: {$result['successful_assessments']}");
            $this->line("Failed: {$result['failed_assessments']}");
            $this->line("High risk pens: {$result['high_risk_pens']}");

            if ($result['high_risk_pens'] > 0) {
                $this->warn("\n⚠ ALERT: {$result['high_risk_pens']} pen(s) at high risk!");
            }

            if (! empty($result['errors'])) {
                $this->warn("\nErrors:");
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
        } else {
            $this->error("✗ Outbreak assessment failed: {$result['error']}");
        }
    }

    private function displaySingleResult(array $result, string $penId): void
    {
        if ($result['success']) {
            $data = $result['data'];
            $cached = $result['cached'] ? '(cached)' : '';
            $riskLevel = $data['risk_level'] ?? 'UNKNOWN';

            if ($riskLevel === 'HIGH') {
                $this->warn("⚠ ALERT - HIGH RISK PEN {$cached}");
            } else {
                $this->info("✓ Assessment successful {$cached}");
            }

            $this->line("Risk Level: {$riskLevel}");
            $this->line("Risk Score: {$data['risk_score']}");
            $this->line("Affected Hogs: {$data['affected_hogs']}");
            $this->line("Confidence: {$data['confidence']}%");

            if (! empty($data['risk_factors'])) {
                $this->line("\nRisk Factors:");
                foreach ($data['risk_factors'] as $factor) {
                    $this->line("  • {$factor}");
                }
            }

            if (! empty($data['recommendations'])) {
                $this->line("\nRecommendations:");
                foreach ($data['recommendations'] as $rec) {
                    $this->line("  → {$rec}");
                }
            }
        } else {
            $this->error("✗ Assessment failed: {$result['error']}");
        }
    }
}
