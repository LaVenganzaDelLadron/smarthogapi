<?php

namespace Tests\Feature;

use App\Models\Farms;
use App\Models\FeedingSchedule;
use App\Models\Hogpens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Farms $farm;

    protected Hogpens $pen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->farm = Farms::create([
            'user_id' => $this->user->id,
            'location' => 'Test Farm',
            'timezone' => 'UTC',
        ]);

        $this->pen = Hogpens::create([
            'farm_id' => $this->farm->id,
            'name' => 'Test Pen',
            'capacity' => 100,
            'status' => 1,
        ]);
    }

    /**
     * Test creating feeding schedule with multiple feeding times (JSON array)
     */
    public function test_create_feeding_schedule_with_multiple_times(): void
    {
        $feedingTimes = ['06:00', '12:00', '18:00'];

        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => $feedingTimes,
            'daily_feeding_count' => count($feedingTimes),
        ]);

        $this->assertDatabaseHas('feeding_schedule', [
            'hog_pen_id' => $this->pen->id,
            'daily_feeding_count' => 3,
        ]);

        $this->assertEquals($feedingTimes, $schedule->feeding_times);
        $this->assertEquals(3, $schedule->daily_feeding_count);
    }

    /**
     * Test getting feeding times count
     */
    public function test_get_feeding_times_count(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => ['06:00', '14:00'],
            'daily_feeding_count' => 2,
        ]);

        $this->assertEquals(2, $schedule->getFeedingTimesCount());
    }

    /**
     * Test checking if specific time exists in schedule
     */
    public function test_has_specific_time(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => ['06:00', '12:00', '18:00'],
            'daily_feeding_count' => 3,
        ]);

        $this->assertTrue($schedule->hasTime('06:00'));
        $this->assertTrue($schedule->hasTime('12:00'));
        $this->assertFalse($schedule->hasTime('09:00'));
    }

    /**
     * Test getting sorted feeding times
     */
    public function test_get_sorted_feeding_times(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => ['18:00', '06:00', '12:00'], // Unsorted
            'daily_feeding_count' => 3,
        ]);

        $sorted = $schedule->getSortedFeedingTimes();
        $this->assertEquals(['06:00', '12:00', '18:00'], $sorted);
    }

    /**
     * Test setting feeding times via helper method
     */
    public function test_set_feeding_times(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
        ]);

        $newTimes = ['08:00', '16:00'];
        $schedule->setFeedingTimes($newTimes);
        $schedule->save();

        $this->assertEquals($newTimes, $schedule->feeding_times);
        $this->assertEquals(2, $schedule->daily_feeding_count);
    }

    /**
     * Test single feeding time (legacy support)
     */
    public function test_single_feeding_time(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'manual',
            'time' => now()->setTime(8, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => ['08:00'],
            'daily_feeding_count' => 1,
        ]);

        $this->assertEquals(1, $schedule->getFeedingTimesCount());
        $this->assertTrue($schedule->hasTime('08:00'));
    }

    /**
     * Test that feeding times are cast as array
     */
    public function test_feeding_times_casting(): void
    {
        $schedule = FeedingSchedule::create([
            'hog_pen_id' => $this->pen->id,
            'mode' => 'auto',
            'time' => now()->setTime(6, 0, 0),
            'feed_amount' => 25.50,
            'feed_type' => 'grower',
            'feeding_times' => ['06:00', '12:00', '18:00'],
            'daily_feeding_count' => 3,
        ]);

        // Retrieve from database
        $retrieved = FeedingSchedule::find($schedule->id);

        // Should be array, not JSON string
        $this->assertIsArray($retrieved->feeding_times);
        $this->assertCount(3, $retrieved->feeding_times);
    }
}
