<?php

namespace Database\Seeders;

use App\Models\Alerts;
use App\Models\DailyFarmReports;
use App\Models\DeviceLogs;
use App\Models\Farms;
use App\Models\Feeders;
use App\Models\FeedingLogs;
use App\Models\FeedingSchedule;
use App\Models\HogDailyRecords;

use App\Models\Hogpens;
use App\Models\Hogs;

use App\Models\IotDevices;
use App\Models\MLModels;
use App\Models\SensorReadings;
use App\Models\Sensors;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Farmer',
            'email' => 'farmer@test.com',
            'password' => bcrypt('password'),
        ]);

        $farm = Farms::create([
            'user_id' => $user->id,
            'location' => 'Test Farm Location',
            'timezone' => 'Asia/Manila',
        ]);

        $hogpen = Hogpens::create([
            'farm_id' => $farm->id,
            'name' => 'Pen 1',
            'capacity' => 100,
            'status' => 1,
        ]);

        $hog = Hogs::create([
            'hog_pen_id' => $hogpen->id,
            'ear_tag_id' => 'H001',
            'breed' => 'Duroc',
            'gender' => 'male',
            'current_age' => 120,
            'weight_current' => 50.5,
        ]);

        // Feeders
        $feeder = Feeders::create([
            'hog_pen_id' => $hogpen->id,
            'device_id' => 1,
            'status' => 'active',
            'last_refill' => now(),
        ]);

        // Sensors
        $sensor = Sensors::create([
            'hog_pen_id' => $hogpen->id,
            'sensor_type' => 'temperature',
            'device_id' => 1,
            'status' => 'active',
        ]);

        // Feeding Schedule with multiple daily feeding times
        FeedingSchedule::create([
            'hog_pen_id' => $hogpen->id,
            'mode' => 'auto',
            'time' => now()->setTime(8, 0, 0),
            'feed_amount' => 25.5,
            'feed_type' => 'grower',
            'feeding_times' => ['06:00', '12:00', '18:00'], // 3 times daily
            'daily_feeding_count' => 3,
        ]);

        // Sensor Reading
        SensorReadings::create([
            'sensor_id' => $sensor->id,
            'value' => 22.5,
            'unit' => 'C',
        ]);

        // Feeding Log
        FeedingLogs::create([
            'feeder_id' => $feeder->id,
            'pen_id' => $hogpen->id,
            'feed_amount_given' => 20.0,
            'triggered' => 'auto',
        ]);

        // IoT Device
        $iotDevice = IotDevices::create([
            'hog_pen_id' => $hogpen->id,
            'type' => 'feeder',
            'api_provider' => 'mock',
            'status' => 'online',
        ]);

        // Device Log
        DeviceLogs::create([
            'device_id' => $iotDevice->id,
            'action' => 'status_check',
            'response' => 'ok',
        ]);

        // ML Model
        $mlModel = MLModels::create([
            'model_name' => 'health_predictor',
            'version' => '1.0',
            'accuracy_score' => 0.92,
        ]);



        // Daily Farm Report
        DailyFarmReports::create([
            'farm_id' => $farm->id,
            'report_date' => now(),
            'total_feed_consumed' => 150.0,
            'total_hogs' => 50,
            'avg_weight' => 45.2,
            'mortality_count' => 0,
        ]);

        // Alert
        Alerts::create([
            'farm_id' => $farm->id,
            'hog_pen_id' => $hogpen->id,
            'type' => 'low_feed',
            'message' => 'Feeder low on feed',
            'severity' => 'warning',
            'status' => 'active',
        ]);

        // Hog Daily Record
        HogDailyRecords::create([
            'hog_id' => $hog->id,
            'hog_pen_id' => $hogpen->id,
            'weight' => 51.0,
            'feed_consumed' => 2.5,
            'health_status' => 1,
            'temperature' => 38.5,
            'activity_level' => 'normal',
            'notes' => 'Healthy hog',
            'recorded_date' => now(),
        ]);
    }
}
