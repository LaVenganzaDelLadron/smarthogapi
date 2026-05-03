<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PredictDeviceRisk extends Command
{
    protected $signature = 'predict:device-risk {--all : Assess all devices} {--device-id= : Assess specific device by ID}';

    protected $description = 'Run predictive maintenance assessments via ML service';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->option('all');
        $deviceId = $this->option('device-id');

        if ($all) {
            $this->info('Running maintenance assessments for all devices...');
            $result = $this->assessAllDevices();

            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        if ($deviceId) {
            $this->info("Assessing maintenance risk for device {$deviceId}...");
            $result = $this->predictionService->predictDeviceRisk((int) $deviceId);

            $this->displaySingleResult($result, $deviceId);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->error('Specify either --all or --device-id=ID');

        return self::INVALID;
    }

    private function assessAllDevices(): array
    {
        $deviceCount = 0;
        $successCount = 0;
        $failureCount = 0;
        $criticalCount = 0;
        $warningCount = 0;
        $errors = [];

        try {
            $devices = DB::table('iot_devices')->pluck('id');

            foreach ($devices as $deviceId) {
                $deviceCount++;

                try {
                    $result = $this->predictionService->predictDeviceRisk($deviceId);

                    if ($result['success']) {
                        $successCount++;
                        $status = $result['data']['status'] ?? 'Normal';
                        if ($status === 'Critical') {
                            $criticalCount++;
                        } elseif ($status === 'Warning') {
                            $warningCount++;
                        }
                    } else {
                        $failureCount++;
                        $errors[] = "Device {$deviceId}: {$result['error']}";
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[] = "Device {$deviceId}: {$e->getMessage()}";
                }
            }

            return [
                'success' => true,
                'total_devices' => $deviceCount,
                'successful_assessments' => $successCount,
                'failed_assessments' => $failureCount,
                'critical_devices' => $criticalCount,
                'warning_devices' => $warningCount,
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
            $this->info('✓ Maintenance assessment completed');
            $this->line("Total devices: {$result['total_devices']}");
            $this->line("Successful: {$result['successful_assessments']}");
            $this->line("Failed: {$result['failed_assessments']}");

            if ($result['critical_devices'] > 0) {
                $this->error("  Critical: {$result['critical_devices']}");
            }
            if ($result['warning_devices'] > 0) {
                $this->warn("  Warning: {$result['warning_devices']}");
            }

            if ($result['critical_devices'] > 0 || $result['warning_devices'] > 0) {
                $totalIssues = $result['critical_devices'] + $result['warning_devices'];
                $this->warn("\n⚠ ACTION REQUIRED: Maintenance needed for {$totalIssues} device(s)!");
            }

            if (! empty($result['errors'])) {
                $this->warn("\nErrors:");
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
        } else {
            $this->error("✗ Maintenance assessment failed: {$result['error']}");
        }
    }

    private function displaySingleResult(array $result, string $deviceId): void
    {
        if ($result['success']) {
            $data = $result['data'];
            $cached = $result['cached'] ? '(cached)' : '';
            $status = $data['status'] ?? 'Normal';

            if ($status === 'Critical') {
                $this->error("🔴 CRITICAL - DEVICE {$deviceId} {$cached}");
            } elseif ($status === 'Warning') {
                $this->warn("🟡 WARNING - DEVICE {$deviceId} {$cached}");
            } else {
                $this->info("✓ Device OK {$cached}");
            }

            $this->line("Status: {$status}");
            $this->line("Maintenance Score: {$data['maintenance_score']}");
            $this->line("Days Until Failure: {$data['days_until_failure']}");
            $this->line("Confidence: {$data['confidence']}%");

            if (! empty($data['issue_indicators'])) {
                $this->line("\nIssue Indicators:");
                foreach ($data['issue_indicators'] as $indicator) {
                    $this->line("  • {$indicator}");
                }
            }

            if (! empty($data['recommendations'])) {
                $this->line("\nAction Items:");
                foreach ($data['recommendations'] as $rec) {
                    $this->line("  → {$rec}");
                }
            }
        } else {
            $this->error("✗ Assessment failed: {$result['error']}");
        }
    }
}
