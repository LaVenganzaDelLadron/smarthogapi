<?php

namespace Tests\Feature\Analytics;

use App\Jobs\GenerateDailyFarmSummaries;
use App\Models\DailyFarmReports;
use App\Models\User;
use App\Services\Analytics\DailyFarmSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DescriptiveAnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_endpoint_returns_descriptive_kpis(): void
    {
        $farm = $this->seedAnalyticsData();

        $response = $this->getJson('/api/v1/analytics/dashboard?farm_id='.$farm['farm_id']);

        $response->assertOk()
            ->assertJsonPath('data.total_hog_count', 2)
            ->assertJsonPath('data.active_pens_count', 1)
            ->assertJsonPath('data.feed_used_today', 30)
            ->assertJsonPath('data.alerts_triggered_today', 2);
    }

    public function test_feed_report_endpoint_returns_aggregated_sections(): void
    {
        $farm = $this->seedAnalyticsData();

        $response = $this->getJson('/api/v1/analytics/feed-report?farm_id='.$farm['farm_id']);

        $response->assertOk()
            ->assertJsonPath('data.highest_feed_consuming_pen.pen_name', 'Pen A')
            ->assertJson(fn ($json) => $json
                ->has('data.daily_feed_usage')
                ->has('data.weekly_feed_usage')
                ->has('data.feed_usage_by_pen')
                ->has('data.feed_usage_by_hog')
                ->etc()
            );
    }

    public function test_growth_environment_alerts_and_ranking_endpoints_return_reports(): void
    {
        $farm = $this->seedAnalyticsData();

        $this->getJson('/api/v1/analytics/growth-report?farm_id='.$farm['farm_id'])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data.weight_trend_per_hog')
                ->has('data.pen_growth_comparison')
                ->has('data.fastest_growing_hogs')
                ->etc()
            );

        $this->getJson('/api/v1/analytics/environment-report?farm_id='.$farm['farm_id'])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data.daily_average_temperature')
                ->has('data.daily_average_humidity')
                ->has('data.gas_level_averages')
                ->has('data.pen_environmental_comparison')
                ->etc()
            );

        $this->getJson('/api/v1/analytics/alerts-report?farm_id='.$farm['farm_id'])
            ->assertOk()
            ->assertJsonPath('data.total_unresolved_alerts', 1);

        $this->getJson('/api/v1/analytics/pen-ranking?farm_id='.$farm['farm_id'])
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data.rankings', 2)
                ->has('data.rankings.0.rank')
                ->etc()
            );
    }

    public function test_daily_summary_job_creates_daily_farm_report(): void
    {
        $farm = $this->seedAnalyticsData();

        $job = new GenerateDailyFarmSummaries(now()->toDateString(), $farm['farm_id']);
        $job->handle(app()->make(DailyFarmSummaryService::class));

        $this->assertDatabaseHas('daily_farm_reports', [
            'farm_id' => $farm['farm_id'],
            'total_hogs' => 2,
            'active_pens' => 1,
            'alerts_triggered' => 2,
        ]);

        $this->assertInstanceOf(DailyFarmReports::class, DailyFarmReports::query()->first());
    }

    /**
     * @return array{farm_id:int}
     */
    private function seedAnalyticsData(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $farmId = DB::table('farms')->insertGetId([
            'user_id' => $user->id,
            'location' => 'North Farm',
            'timezone' => 'Asia/Manila',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $penAId = DB::table('hog_pens')->insertGetId([
            'farm_id' => $farmId,
            'name' => 'Pen A',
            'capacity' => 10,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $penBId = DB::table('hog_pens')->insertGetId([
            'farm_id' => $farmId,
            'name' => 'Pen B',
            'capacity' => 10,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hogOneId = DB::table('hogs')->insertGetId([
            'hog_pen_id' => $penAId,
            'ear_tag_id' => 'HOG-001',
            'breed' => 'Large White',
            'gender' => 'female',
            'current_age' => 12,
            'weight_current' => 55,
            'health_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hogTwoId = DB::table('hogs')->insertGetId([
            'hog_pen_id' => $penBId,
            'ear_tag_id' => 'HOG-002',
            'breed' => 'Landrace',
            'gender' => 'male',
            'current_age' => 11,
            'weight_current' => 48,
            'health_status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('feeders')->insert([
            [
                'id' => 1,
                'hog_pen_id' => $penAId,
                'device_id' => 101,
                'status' => 'active',
                'last_refill' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'hog_pen_id' => $penBId,
                'device_id' => 102,
                'status' => 'active',
                'last_refill' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('feeding_logs')->insert([
            [
                'feeder_id' => 1,
                'pen_id' => $penAId,
                'feed_amount_given' => 20,
                'triggered' => 'auto',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'feeder_id' => 2,
                'pen_id' => $penBId,
                'feed_amount_given' => 10,
                'triggered' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sensors')->insert([
            [
                'id' => 1,
                'hog_pen_id' => $penAId,
                'sensor_type' => 'temperature',
                'device_id' => 201,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'hog_pen_id' => $penAId,
                'sensor_type' => 'humidity',
                'device_id' => 202,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'hog_pen_id' => $penBId,
                'sensor_type' => 'gas',
                'device_id' => 203,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sensor_readings')->insert([
            [
                'sensor_id' => 1,
                'value' => 28,
                'unit' => 'C',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sensor_id' => 2,
                'value' => 70,
                'unit' => '%',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sensor_id' => 3,
                'value' => 35,
                'unit' => 'ppm',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('alerts')->insert([
            [
                'farm_id' => $farmId,
                'hog_pen_id' => $penAId,
                'type' => 'temperature',
                'message' => 'High temperature detected.',
                'severity' => 'high',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'farm_id' => $farmId,
                'hog_pen_id' => $penBId,
                'type' => 'health',
                'message' => 'Low activity detected.',
                'severity' => 'medium',
                'status' => 'resolved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([0, 3, 6] as $dayOffset) {
            DB::table('hog_daily_records')->insert([
                [
                    'hog_id' => $hogOneId,
                    'hog_pen_id' => $penAId,
                    'weight' => 50 + $dayOffset,
                    'feed_consumed' => 5 + $dayOffset,
                    'health_status' => 'healthy',
                    'temperature' => 38.5,
                    'activity_level' => 'normal',
                    'notes' => 'Stable',
                    'recorded_date' => Carbon::now()->subDays(6 - $dayOffset),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'hog_id' => $hogTwoId,
                    'hog_pen_id' => $penBId,
                    'weight' => 46 + $dayOffset,
                    'feed_consumed' => 4 + $dayOffset,
                    'health_status' => 'sick',
                    'temperature' => 39.1,
                    'activity_level' => 'low',
                    'notes' => 'Needs monitoring',
                    'recorded_date' => Carbon::now()->subDays(6 - $dayOffset),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        return ['farm_id' => $farmId];
    }
}
