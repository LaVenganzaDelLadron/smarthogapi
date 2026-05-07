# Feeding Times Configuration Guide

## Overview

The SmartHog API now supports configurable feeding times for each hog pen. Users can specify 1, 2, 3, or more feeding times per day, stored as a JSON array in the `feeding_schedule` table.

## Database Schema

### Updated `feeding_schedule` Table

```
| Column              | Type       | Description                                    |
|-------------------|------------|-----------------------------------------------|
| id                | bigint     | Primary key                                    |
| hog_pen_id        | bigint     | Foreign key to hog_pens table                 |
| time              | datetime   | Legacy column (first feeding time)             |
| feeding_times     | json       | Array of times in HH:MM format                 |
| daily_feeding_count | tinyint  | Number of feeding times per day                |
| feed_amount       | decimal    | Total feed per day (in kg)                     |
| feed_type         | varchar    | Type of feed (grower, starter, finisher, etc.) |
| mode              | varchar    | auto, manual, or scheduled                     |
| created_at        | timestamp  | Created timestamp                              |
| updated_at        | timestamp  | Updated timestamp                              |
```

## Creating a Feeding Schedule

### Basic Usage

```php
use App\Models\FeedingSchedule;

// Create feeding schedule with 3 daily feeding times
$schedule = FeedingSchedule::create([
    'hog_pen_id' => 1,
    'mode' => 'auto',
    'time' => now()->setTime(6, 0, 0),  // First feeding time
    'feed_amount' => 25.50,              // Total daily amount
    'feed_type' => 'grower',
    'feeding_times' => ['06:00', '12:00', '18:00'],  // All daily times
    'daily_feeding_count' => 3,
]);
```

### Using Helper Methods

```php
// Set feeding times with automatic count update
$schedule = FeedingSchedule::find(1);
$schedule->setFeedingTimes(['08:00', '16:00']);  // 2 times per day
$schedule->save();

// Retrieve feeding times count
$count = $schedule->getFeedingTimesCount();  // Returns: 2

// Check if specific time exists
$hasTime = $schedule->hasTime('08:00');  // Returns: true

// Get sorted feeding times
$sorted = $schedule->getSortedFeedingTimes();  // Returns: ['08:00', '16:00']
```

## Integration with FastAPI

The FastAPI integration automatically extracts feeding times from the database:

```php
use App\Services\FastAPIIntegration;

$service = new FastAPIIntegration();

// When calling predictFeedRecommendation, the feeding_times are automatically
// extracted from the pen's feeding schedule and sent to FastAPI
$result = $service->predictFeedRecommendation($penId);

// The payload sent to FastAPI includes:
// {
//   "pig_age_days": 28,
//   "avg_weight_kg": 7.5,
//   "growth_stage": "hog pre-starter",
//   "current_feed_kg": 0.6,
//   "pen_capacity": 8,
//   "device_code": "auto_feeder_v1",
//   "feeding_times": ["06:00", "12:00", "18:00"],  // From feeding_schedule.feeding_times
//   "num_pens": 1,
//   "feed_type": "hog pre-starter"
// }
```

## API Examples

### Create Feeding Schedule (REST API)

```http
POST /api/feeding-schedules
Content-Type: application/json

{
  "hog_pen_id": 1,
  "mode": "auto",
  "time": "2026-05-07T06:00:00Z",
  "feed_amount": 25.50,
  "feed_type": "grower",
  "feeding_times": ["06:00", "12:00", "18:00"],
  "daily_feeding_count": 3
}
```

### Update Feeding Times

```http
PATCH /api/feeding-schedules/1
Content-Type: application/json

{
  "feeding_times": ["07:00", "13:00", "19:00"],
  "daily_feeding_count": 3
}
```

### Get Feeding Schedule with Times

```http
GET /api/feeding-schedules/1

Response:
{
  "id": 1,
  "hog_pen_id": 1,
  "mode": "auto",
  "time": "2026-05-07T06:00:00Z",
  "feed_amount": "25.50",
  "feed_type": "grower",
  "feeding_times": ["06:00", "12:00", "18:00"],
  "daily_feeding_count": 3,
  "created_at": "2026-05-07T06:25:34Z",
  "updated_at": "2026-05-07T06:25:34Z"
}
```

## Common Feeding Patterns

### Once Daily Feeding
```php
FeedingSchedule::create([
    'hog_pen_id' => $penId,
    'mode' => 'auto',
    'feeding_times' => ['08:00'],
    'daily_feeding_count' => 1,
    'feed_amount' => 5.0,
]);
```

### Twice Daily (Morning & Evening)
```php
FeedingSchedule::create([
    'hog_pen_id' => $penId,
    'mode' => 'auto',
    'feeding_times' => ['06:00', '18:00'],
    'daily_feeding_count' => 2,
    'feed_amount' => 10.0,
]);
```

### Thrice Daily (Starter Feed Pattern)
```php
FeedingSchedule::create([
    'hog_pen_id' => $penId,
    'mode' => 'auto',
    'feeding_times' => ['06:00', '12:00', '18:00'],
    'daily_feeding_count' => 3,
    'feed_amount' => 15.0,
]);
```

### Four Times Daily (Intensive Care)
```php
FeedingSchedule::create([
    'hog_pen_id' => $penId,
    'mode' => 'auto',
    'feeding_times' => ['06:00', '10:00', '14:00', '18:00'],
    'daily_feeding_count' => 4,
    'feed_amount' => 20.0,
]);
```

## Model Methods Reference

### `getFeedingTimesCount(): int`
Returns the number of daily feeding times.

```php
$count = $schedule->getFeedingTimesCount();  // 3
```

### `hasTime(string $timeString): bool`
Checks if a specific time is in the feeding schedule.

```php
if ($schedule->hasTime('12:00')) {
    // Handle midday feeding
}
```

### `getSortedFeedingTimes(): array`
Returns feeding times sorted in ascending order.

```php
$times = $schedule->getSortedFeedingTimes();
// Returns: ['06:00', '12:00', '18:00']
```

### `setFeedingTimes(array $times): void`
Sets feeding times and automatically updates the daily count.

```php
$schedule->setFeedingTimes(['07:00', '13:00', '19:00']);
$schedule->save();
```

## Backward Compatibility

The system maintains backward compatibility with the old structure where each feeding time was stored as a separate row:

```php
// Old structure (still supported):
FeedingSchedule::create(['hog_pen_id' => 1, 'time' => now()->setTime(6, 0)]);
FeedingSchedule::create(['hog_pen_id' => 1, 'time' => now()->setTime(12, 0)]);
FeedingSchedule::create(['hog_pen_id' => 1, 'time' => now()->setTime(18, 0)]);

// New structure (recommended):
FeedingSchedule::create([
    'hog_pen_id' => 1,
    'feeding_times' => ['06:00', '12:00', '18:00']
]);
```

## Testing

The functionality includes comprehensive tests:

```bash
php artisan test tests/Feature/FeedingScheduleTest.php
```

Tests cover:
- Creating schedules with multiple feeding times
- Retrieving and counting feeding times
- Checking if specific times exist
- Sorting feeding times
- Setting feeding times with helper methods
- Casting JSON to arrays
- Single and multiple feeding time scenarios
