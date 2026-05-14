<?php

use App\Http\Controllers\ActivityLogsController;
use App\Http\Controllers\AlertsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyFarmReportsController;
use App\Http\Controllers\DeviceActionsController;
use App\Http\Controllers\DeviceCommandController;
use App\Http\Controllers\DeviceCredentialsController;
use App\Http\Controllers\DeviceLogsController;
use App\Http\Controllers\FarmsController;
use App\Http\Controllers\FeedersController;
use App\Http\Controllers\FeedingLogsController;
use App\Http\Controllers\FeedingPredictionsController;
use App\Http\Controllers\FeedingQueueController;
use App\Http\Controllers\FeedingScheduleController;
use App\Http\Controllers\HogDailyRecordsController;
use App\Http\Controllers\HogPensController;
use App\Http\Controllers\HogsController;
use App\Http\Controllers\IotDevicesController;
use App\Http\Controllers\MlModelsController;
use App\Http\Controllers\SensorReadingsController;
use App\Http\Controllers\SensorsController;
use App\Services\PredictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
});

Route::prefix('/v1')->group(function () {
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // FastAPI health check (no auth required)
    Route::get('/predictions/health', [PredictionController::class, 'health']);

    Route::middleware('device.auth:commands:poll')->group(function () {
        Route::get('/iot-devices/{iotDevice}/next-command', [DeviceCommandController::class, 'next']);
    });

    Route::middleware('device.auth:commands:complete')->group(function () {
        Route::post('/device-commands/{deviceCommand}/complete', [DeviceCommandController::class, 'complete']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/users/myself', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user(),
            ]);
        });

        Route::get('/activitylogs', [ActivityLogsController::class, 'index']);
        Route::get('/activitylogs/device/{iotDevice}', [ActivityLogsController::class, 'forDevice']);

        Route::post('/iot-devices/{iotDevice}/action', [DeviceActionsController::class, 'store']);
        Route::get('/device-credentials', [DeviceCredentialsController::class, 'index']);
        Route::post('/device-credentials', [DeviceCredentialsController::class, 'store']);
        Route::delete('/device-credentials/{deviceCredential}', [DeviceCredentialsController::class, 'destroy']);

        Route::prefix('analytics')->controller(AnalyticsController::class)->group(function () {
            Route::get('/dashboard', 'dashboard');
            Route::get('/feed-report', 'feedReport');
            Route::get('/growth-report', 'growthReport');
            Route::get('/environment-report', 'environmentReport');
            Route::get('/alerts-report', 'alertsReport');
            Route::get('/pen-ranking', 'penRanking');
        });

        Route::prefix('predictions')->group(function () {
            Route::post('/hog-health/{hogId}', function (int $hogId, PredictionService $service) {
                $result = $service->predictHogHealth($hogId);

                return response()->json($result, $result['success'] ? 201 : 400);
            });

            // FastAPI prediction endpoints
            Route::post('/feed-recommendation', [PredictionController::class, 'feedRecommendation']);
            Route::post('/weight-trend', [PredictionController::class, 'weightTrend']);
            Route::post('/pen-status', [PredictionController::class, 'penStatus']);
            Route::post('/batch/feed-recommendation', [PredictionController::class, 'batchFeedRecommendation']);
            Route::post('/batch/weight-trend', [PredictionController::class, 'batchWeightTrend']);
            Route::post('/batch/pen-status', [PredictionController::class, 'batchPenStatus']);
        });

        Route::apiResource('farms', FarmsController::class);
        Route::apiResource('hogpens', HogPensController::class);
        Route::apiResource('hogs', HogsController::class);
        Route::apiResource('feeders', FeedersController::class);
        Route::apiResource('feeding-logs', FeedingLogsController::class);
        Route::apiResource('feeding-schedule', FeedingScheduleController::class);
        Route::apiResource('sensors', SensorsController::class);
        Route::apiResource('sensor-readings', SensorReadingsController::class);
        Route::apiResource('daily-farm-reports', DailyFarmReportsController::class);
        Route::apiResource('alerts', AlertsController::class);
        Route::apiResource('iot-devices', IotDevicesController::class);
        Route::apiResource('device-logs', DeviceLogsController::class);
        Route::apiResource('hog-daily-records', HogDailyRecordsController::class);

        Route::apiResource('ml-models', MlModelsController::class);
        Route::apiResource('feeding-predictions', FeedingPredictionsController::class);

        // ESP32 Feeding Queue Routes
        Route::prefix('feeding-queue')->controller(FeedingQueueController::class)->group(function () {
            Route::get('/', 'index'); // List all jobs (debugging)
            Route::get('/{feedingQueue}', 'show'); // Get specific job
            Route::post('/next-job', 'nextJob'); // Get next pending job for ESP32
            Route::patch('/{feedingQueue}', 'update'); // Update job status after execution
        });

        Route::prefix('feeders')->controller(FeedingQueueController::class)->group(function () {
            Route::get('/{feeder}/relay-config', 'getRelayConfig'); // Get relay config for ESP32
        });
    });
});
