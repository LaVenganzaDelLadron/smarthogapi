# ESP32 Relay Control System - Database & API Design

## Overview

This document describes the database schema and control flow for an ESP32-based automated feeding system using a 4-relay module. The system manages feed dispensing for different hog growth stages (starter, grower, finisher, maintenance) with hardware-level relay control.

## Hardware Setup

**ESP32 Microcontroller**: Controls GPIO pins connected to 4-relay module
**Relay Module**: 4-channel relay shield with GPIO pins:
- Relay 1 (GPIO 12): Starter feed motor
- Relay 2 (GPIO 14): Grower feed motor  
- Relay 3 (GPIO 27): Finisher feed motor
- Relay 4 (GPIO 26): Maintenance/supplement feed motor

**Safety Features**:
- Max duration timeout (15-30 seconds) prevents motor overrun
- Status tracking (pending/processing/completed/error) for audit trail
- Error message logging for debugging

## Database Schema

### Tables

#### `feeder_feed_type_mapping`
Maps feeder hardware to specific feed types and GPIO pins.

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint(20) | Primary key |
| feeder_id | bigint(20) | FK to feeders table |
| feed_type | varchar(255) | 'starter', 'grower', 'finisher', 'maintenance' |
| relay_pin | int(10) | GPIO pin number (12, 14, 27, 26) |
| max_duration_seconds | int(10) | Safety timeout (default: 30s) |
| is_active | boolean | Enables/disables feed type |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last modification |

**Unique Index**: `(feeder_id, feed_type)` - One relay pin per feed type per feeder

**Sample Data**:
```
Feeder 1 → Starter    → GPIO 12 → 20s max
Feeder 1 → Grower     → GPIO 14 → 25s max
Feeder 1 → Finisher   → GPIO 27 → 30s max
Feeder 1 → Maintenance → GPIO 26 → 15s max
```

#### `feeding_queue`
Queued feeding operations awaiting ESP32 execution.

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint(20) | Primary key |
| feeder_id | bigint(20) | FK to feeders table |
| hog_pen_id | bigint(20) | FK to hog_pens table |
| feed_type | varchar(255) | Type of feed to dispense |
| scheduled_at | timestamp | When feeding should occur |
| actual_feed_time | timestamp | When feeding actually occurred |
| status | varchar(255) | pending/processing/completed/skipped/error |
| duration_seconds | int(10) | How long relay was active |
| amount_dispensed | decimal(8,2) | Feed amount (kg) dispensed |
| error_message | varchar(255) | Failure reason if status=error |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Last update |

**Status Values**:
- `pending`: Waiting for ESP32 to process
- `processing`: Relay active, motor running
- `completed`: Successfully dispensed
- `skipped`: Bypassed (maintenance, manual override)
- `error`: Failed (timeout, relay fault, etc.)

## Control Flow

### 1. Schedule Creation (Laravel Backend)

```
[HogHealthPredictions updated]
           ↓
[Laravel schedule detects growth stage change]
           ↓
[PredictionService evaluates age/weight]
           ↓
[Determines current stage: starter → grower → finisher]
           ↓
[FeedingQueueService::createFromSchedule()]
           ↓
INSERT INTO feeding_queue VALUES (
  feeder_id=1,
  hog_pen_id=1,
  feed_type='grower',  // ← New stage
  scheduled_at=NOW(),
  status='pending'
)
```

### 2. ESP32 Polling (Microcontroller)

**Polling Interval**: Every 5-10 seconds via HTTP

```
[ESP32 startup]
    ↓
[WiFi connect]
    ↓
[Loop every 10s]:
    POST http://laravel-api/api/feeding-queue/next-job
    {
        "feeder_id": 1,
        "max_jobs": 1
    }
    ↓
Server response:
    {
        "jobs": [
            {
                "id": 123,
                "feed_type": "grower",
                "relay_pin": 14,
                "max_duration_seconds": 25,
                "hog_pen_id": 1
            }
        ]
    }
```

### 3. Relay Activation (Microcontroller Hardware)

```
[ESP32 receives job from API]
    ↓
[Update status to 'processing']
    PATCH /api/feeding-queue/123
    { "status": "processing" }
    ↓
[Activate GPIO pin]
    digitalWrite(14, HIGH)  // Relay 2 energizes
    ↓
[Motor runs for duration]
    delay(25_000)           // 25 second timeout
    ↓
[Deactivate GPIO pin]
    digitalWrite(14, LOW)   // Relay disengages
    ↓
[Record completion]
    PATCH /api/feeding-queue/123
    {
        "status": "completed",
        "duration_seconds": 25,
        "actual_feed_time": "2026-05-02T10:15:30Z",
        "amount_dispensed": 2.5
    }
    ↓
[Next poll starts cycle]
```

### 4. Error Handling

**Timeout Scenario** (Motor doesn't stop):
```
if (elapsed_time > relay_pin->max_duration_seconds) {
    digitalWrite(relay_pin, LOW)      // Force disable
    
    PATCH /api/feeding-queue/123
    {
        "status": "error",
        "error_message": "Exceeded max duration 25s",
        "duration_seconds": 26
    }
    
    // Alert: Alert::create([
    //    'farm_id' => 1,
    //    'hog_pen_id' => 1,
    //    'type' => 'relay_timeout',
    //    'message' => 'Grower feed motor exceeded 25s limit',
    //    'severity' => 'warning'
    // ])
}
```

**Network Error** (Can't reach API):
```
[Retry with exponential backoff]
Attempt 1: wait 5s
Attempt 2: wait 10s
Attempt 3: wait 20s

After 3 failures:
    Use last_known_config (cached in ESP32 EEPROM)
    OR skip job and retry next cycle
```

### 5. Growth Stage Transition

As hogs age, the system automatically transitions to the next feed type:

```
[Hog age reaches 28 days]
    ↓
[HogHealthPredictions ML model detects growth milestone]
    ↓
[PredictionService maps to 'grower' stage]
    ↓
[FeedingSchedule updated]
    ↓
[FeedingQueueService creates new queue entries]
    ↓
[ESP32 fetches grower feed config (GPIO 14, 25s duration)]
    ↓
[Automatically dispenses grower feed henceforth]
```

## API Endpoints

### Get Next Feeding Job

**Endpoint**: `POST /api/feeding-queue/next-job`

**Request**:
```json
{
    "feeder_id": 1,
    "max_jobs": 1
}
```

**Response** (200 OK):
```json
{
    "jobs": [
        {
            "id": 123,
            "feed_type": "grower",
            "relay_pin": 14,
            "max_duration_seconds": 25,
            "hog_pen_id": 1,
            "scheduled_at": "2026-05-02T10:00:00Z"
        }
    ]
}
```

### Update Job Status

**Endpoint**: `PATCH /api/feeding-queue/{id}`

**Request**:
```json
{
    "status": "completed",
    "duration_seconds": 25,
    "actual_feed_time": "2026-05-02T10:15:30Z",
    "amount_dispensed": 2.5
}
```

**Response** (200 OK):
```json
{
    "id": 123,
    "status": "completed",
    "updated_at": "2026-05-02T10:15:31Z"
}
```

### Get Relay Configuration

**Endpoint**: `GET /api/feeders/{feeder_id}/relay-config`

**Response** (200 OK):
```json
{
    "feeder_id": 1,
    "relays": [
        {
            "feed_type": "starter",
            "relay_pin": 12,
            "max_duration_seconds": 20
        },
        {
            "feed_type": "grower",
            "relay_pin": 14,
            "max_duration_seconds": 25
        },
        {
            "feed_type": "finisher",
            "relay_pin": 27,
            "max_duration_seconds": 30
        },
        {
            "feed_type": "maintenance",
            "relay_pin": 26,
            "max_duration_seconds": 15
        }
    ]
}
```

## Laravel Models

### FeederFeedTypeMapping

```php
$mapping = FeederFeedTypeMapping::where('feeder_id', 1)
    ->where('feed_type', 'grower')
    ->firstOrFail();

echo $mapping->relay_pin;              // 14
echo $mapping->max_duration_seconds;   // 25
```

### FeedingQueue

```php
// Get pending jobs
$pending = FeedingQueue::pending()->get();

// Get completed jobs today
$completed = FeedingQueue::completed()
    ->whereDate('actual_feed_time', today())
    ->get();

// Create new feeding job
FeedingQueue::create([
    'feeder_id' => 1,
    'hog_pen_id' => 1,
    'feed_type' => 'grower',
    'scheduled_at' => now()->addMinutes(5),
    'status' => 'pending',
]);
```

## Automation Workflow

### Daily Growth Stage Check (02:00 AM)

1. **PredictionService::predictAllHogs()** runs
   - Evaluates each hog's age, weight, health
   - ML model predicts current growth stage
   - Updates HogHealthPredictions table

2. **Console Command**: `schedule:update-feeding-queue`
   - Checks if hog stage changed
   - If `stage_transition`, create FeedingQueue entries
   - Set `scheduled_at` = next feeding time
   - Status = `pending`

3. **ESP32 Polling** (every 10 seconds)
   - Fetches pending jobs
   - Activates relay
   - Reports completion

## Monitoring & Alerts

### Query for Issues

```sql
-- Feeding jobs with errors
SELECT * FROM feeding_queue 
WHERE status='error' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Relays exceeding timeout
SELECT * FROM feeding_queue 
WHERE duration_seconds > max_duration_seconds;

-- Pending jobs older than 1 hour
SELECT * FROM feeding_queue 
WHERE status='pending' AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

### Alert Conditions

- Relay timeout (duration > max_duration_seconds)
- Job pending for >1 hour
- Status='error' with message
- Missing relay configuration

## Security Considerations

1. **ESP32 Authentication**: Use Sanctum tokens or API keys
2. **Input Validation**: Validate feeder_id against farm ownership
3. **Authorization**: Only allow farm owner to fetch their feeder jobs
4. **Rate Limiting**: Cap ESP32 polling requests per feeder
5. **Data Sanitization**: Sanitize error_message before storage

## Future Enhancements

- [ ] Real-time WebSocket updates instead of polling
- [ ] Batch job processing (multiple relays simultaneously)
- [ ] Predictive queue pre-population based on schedule
- [ ] Motor current monitoring for jam detection
- [ ] MQTT integration for better IoT scalability
