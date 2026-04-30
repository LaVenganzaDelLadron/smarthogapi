# ESP32 Automated Feeding System - Setup & API Guide

## Quick Start

### 1. Database Tables Created

✅ **feeder_feed_type_mapping** - Maps feeders to GPIO relays
- 4 feed types configured (starter, grower, finisher, maintenance)
- GPIO pins: 12, 14, 27, 26
- Safety timeouts: 15-30 seconds per type

✅ **feeding_queue** - Queues feeding operations
- Status tracking: pending, processing, completed, error
- Automatic audit trail with timestamps
- Error logging for debugging

✅ **Models Created**
- `FeederFeedTypeMapping` with relations
- `FeedingQueue` with scopes (pending(), processing(), completed())

✅ **Service Layer**
- `FeedingQueueService` - Business logic for job management
- Methods: `getNextJobs()`, `updateJobStatus()`, `createFromSchedule()`, `getRelayConfig()`, `handleStalledJobs()`, `checkTimeoutViolations()`

✅ **API Controller**
- `FeedingQueueController` - RESTful endpoints for ESP32

✅ **Routes Registered**
- `POST /api/v1/feeding-queue/next-job` - ESP32 polls for jobs
- `GET /api/v1/feeders/{id}/relay-config` - ESP32 caches relay configs
- `PATCH /api/v1/feeding-queue/{id}` - ESP32 reports completion
- `GET /api/v1/feeding-queue` - Debugging/monitoring

### 2. Verify Database

```bash
# Check feeder mappings
mysql> SELECT * FROM feeder_feed_type_mapping;
```

Expected output:
```
| id | feeder_id | feed_type    | relay_pin | max_duration_seconds |
|----|-----------|--------------|-----------|----------------------|
| 1  | 1         | starter      | 12        | 20                   |
| 2  | 1         | grower       | 14        | 25                   |
| 3  | 1         | finisher     | 27        | 30                   |
| 4  | 1         | maintenance  | 26        | 15                   |
```

### 3. Test the API

#### Create a feeding queue entry (manual)

```bash
POST /api/v1/feeding-queue

# Or via Tinker:
php artisan tinker
> App\Models\FeedingQueue::create([
    'feeder_id' => 1,
    'hog_pen_id' => 1,
    'feed_type' => 'grower',
    'scheduled_at' => now(),
    'status' => 'pending'
  ]);
```

#### Fetch next job (ESP32 polls this)

```bash
POST /api/v1/feeding-queue/next-job

{
  "feeder_id": 1,
  "max_jobs": 1
}

# Response:
{
  "jobs": [
    {
      "id": 1,
      "feed_type": "grower",
      "relay_pin": 14,
      "max_duration_seconds": 25,
      "hog_pen_id": 1,
      "scheduled_at": "2026-05-02T10:00:00Z"
    }
  ],
  "count": 1
}
```

#### Update job status (ESP32 reports back)

```bash
PATCH /api/v1/feeding-queue/1

{
  "status": "completed",
  "duration_seconds": 25,
  "actual_feed_time": "2026-05-02T10:15:30Z",
  "amount_dispensed": 2.5
}
```

#### Get relay configuration

```bash
GET /api/v1/feeders/1/relay-config

# Response:
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
    ...
  ]
}
```

### 4. ESP32 Setup

**File**: `ESP32_FEEDING_SYSTEM.ino`

**Requirements**:
- Arduino IDE with ESP32 board support
- Libraries: WiFi, HTTPClient, ArduinoJson

**Configuration**:
```cpp
const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";
const char* serverUrl = "http://192.168.1.100:8000/api/v1";
const char* apiToken = "YOUR_SANCTUM_TOKEN";
```

**Hardware Connections**:
```
ESP32 GPIO 12 → Relay Module CH1 (Starter Feed)
ESP32 GPIO 14 → Relay Module CH2 (Grower Feed)
ESP32 GPIO 27 → Relay Module CH3 (Finisher Feed)
ESP32 GPIO 26 → Relay Module CH4 (Maintenance Feed)

Relay Module COM → Motor Power (5V/12V)
Relay Module NO  → Motor Supply to Feeder Motor
GND → Common Ground
```

**Upload Steps**:
1. Install board: `esp32:esp32 by Espressif Systems`
2. Select Board: `ESP32 Dev Module`
3. Upload `ESP32_FEEDING_SYSTEM.ino`
4. Monitor serial output for connection & job execution

### 5. Generate Sanctum Token (API Authentication)

```bash
php artisan tinker

> $user = App\Models\User::first();
> $token = $user->createToken('esp32-feeder')->plainTextToken;
> echo $token;
# Copy this token to ESP32 code
```

### 6. Monitor Feeding Jobs

**Real-time monitoring**:
```bash
# Watch pending jobs
php artisan tinker
> App\Models\FeedingQueue::pending()->get();

# Watch completed jobs today
> App\Models\FeedingQueue::completed()->whereDate('created_at', today())->get();

# Check for errors
> App\Models\FeedingQueue::where('status', 'error')->get();
```

**Laravel logs**:
```bash
tail -f storage/logs/laravel.log
```

### 7. Automation Integration

When hogs transition to new growth stage, automatically create feeding queue entries:

```php
// In PredictionService or scheduled command:
if ($hog->growth_stage_changed) {
    $service = app(FeedingQueueService::class);
    
    $service->createFromSchedule(
        hogPenId: $hog->hog_pen_id,
        feedType: $newStage // 'starter' → 'grower' → 'finisher'
    );
}
```

### 8. Alerts & Monitoring Queries

**Find stalled jobs** (pending > 1 hour):
```sql
SELECT * FROM feeding_queue 
WHERE status='pending' AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

**Find timeout violations** (motor exceeded max duration):
```sql
SELECT fq.*, fm.max_duration_seconds
FROM feeding_queue fq
JOIN feeder_feed_type_mapping fm ON fq.feeder_id=fm.feeder_id AND fq.feed_type=fm.feed_type
WHERE fq.status='completed' AND fq.duration_seconds > fm.max_duration_seconds;
```

**Daily feeding summary**:
```sql
SELECT feed_type, COUNT(*) as count, SUM(amount_dispensed) as total_dispensed
FROM feeding_queue
WHERE status='completed' AND DATE(actual_feed_time) = CURDATE()
GROUP BY feed_type;
```

### 9. Troubleshooting

**ESP32 not connecting**:
- Verify WiFi SSID/password
- Check firewall allows HTTP on port 8000
- Ensure Laravel app is running: `php artisan serve`

**Jobs not fetching**:
- Verify Sanctum token is valid
- Check `Authorization: Bearer TOKEN` header
- Check Laravel logs: `storage/logs/laravel.log`

**Relay not activating**:
- Verify GPIO pins in database match wiring
- Check relay module power supply
- Test GPIO with multimeter

**API errors**:
- 422: Validation error (check JSON payload)
- 401: Invalid/missing token
- 404: Resource not found
- 500: Server error (check logs)

### 10. Growth Stage Automation Example

```php
// Schedule daily at 02:00 AM - Update feeding queues based on growth stage
Schedule::command('schedule:update-feeding-queue')
    ->dailyAt('02:00')
    ->name('update-feeding-queue');

// Command logic:
// 1. Run PredictionService::predictAllHogs()
// 2. Detect growth_stage changes in HogHealthPredictions
// 3. For each hog with stage transition:
//    - Call FeedingQueueService::createFromSchedule(hog_pen_id, new_stage)
//    - Create queue entry with status='pending'
// 4. Next ESP32 poll fetches new stage config & updates relay
```

---

**System Architecture**: 
Laravel Backend ↔ HTTP API ↔ ESP32 ↔ GPIO Relays ↔ Feed Motors

**Data Flow**:
HogHealthPredictions → FeedingSchedule → FeedingQueue → ESP32 Poll → Relay Activation → Motor Control → FeedingLogs
