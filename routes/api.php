<?php

use App\Http\Controllers\AlertsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyFarmReportsController;
use App\Http\Controllers\DeviceLogsController;
use App\Http\Controllers\FarmsController;
use App\Http\Controllers\FeedersController;
use App\Http\Controllers\FeedingLogsController;
use App\Http\Controllers\FeedingPredictionsController;
use App\Http\Controllers\FeedingScheduleController;
use App\Http\Controllers\HogDailyRecordsController;
use App\Http\Controllers\HogHealthPredictionsController;
use App\Http\Controllers\HogPensController;
use App\Http\Controllers\HogsController;
use App\Http\Controllers\IotDevicesController;
use App\Http\Controllers\MlModelsController;
use App\Http\Controllers\SensorReadingsController;
use App\Http\Controllers\SensorsController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('analytics')->controller(AnalyticsController::class)->group(function () {
            Route::get('/dashboard', 'dashboard');
            Route::get('/feed-report', 'feedReport');
            Route::get('/growth-report', 'growthReport');
            Route::get('/environment-report', 'environmentReport');
            Route::get('/alerts-report', 'alertsReport');
            Route::get('/pen-ranking', 'penRanking');
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
        Route::apiResource('hog-health-predictions', HogHealthPredictionsController::class);
        Route::apiResource('ml-models', MlModelsController::class);
        Route::apiResource('feeding-predictions', FeedingPredictionsController::class);
    });
});
