# ESP32 Feeding System - API Testing Guide

## System Status ✅

**Database Tables**: ✅ Created and seeded
- `feeder_feed_type_mapping`: 4 rows (GPIO pins 12, 14, 26, 27)
- `feeding_queue`: 2 test jobs created (pending, ready for ESP32)

**Models**: ✅ Implemented
- `FeederFeedTypeMapping` - relations to Feeders
- `FeedingQueue` - relations to Feeders, Hogpens, scopes for status filtering

**Service Layer**: ✅ Implemented
- `FeedingQueueService` - 6 core methods
- `PredictionService` - health predictions with ML integration

**API Routes**: ✅ Registered
- 5 endpoints for ESP32 communication

**Arduino Sketch**: ✅ Provided
- Ready-to-upload ESP32 firmware with WiFi, HTTP, relay control

---

## Test Scenarios

### Scenario 1: ESP32 Polls for Next Job (HTTP POST)

**Endpoint**: `POST /api/v1/feeding-queue/next-job`

**Header**:
```
Authorization: Bearer YOUR_SANCTUM_TOKEN
Content-Type: application/json
```

**Request Body**:
```json
{
  "feeder_id": 1,
  "max_jobs": 1
}
```

**Expected Response** (200 OK):
```json
{
  "jobs": [
    {
      "id": 1,
      "feed_type": "grower",
      "relay_pin": 14,
      "max_duration_seconds": 25,
      "hog_pen_id": 1,
      "scheduled_at": "2026-04-30T06:45:32Z"
    }
  ],
  "count": 1
}
```

**What happens in ESP32**:
1. Receives `relay_pin: 14` → Maps to GPIO 14
2. Receives `max_duration_seconds: 25` → Sets motor timeout
3. Sets job state to "processing"
4. Activates GPIO 14 (relay closes, motor runs)
5. Waits 25 seconds
6. Deactivates GPIO 14 (relay opens, motor stops)
7. Reports completion to API

**Test via cURL**:
```bash
curl -X POST http://localhost:8000/api/v1/feeding-queue/next-job \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"feeder_id": 1, "max_jobs": 1}'
```

**Test via Laravel Tinker**:
```bash
php artisan tinker

> $service = app(App\Services\FeedingQueueService::class);
> $jobs = $service->getNextJobs(1, 1);
> foreach ($jobs as $job) { var_dump($job); }
```

---

### Scenario 2: ESP32 Reports Completion (HTTP PATCH)

**Endpoint**: `PATCH /api/v1/feeding-queue/{id}`

**Path Parameter**: `id = 1` (from previous response)

**Header**:
```
Authorization: Bearer YOUR_SANCTUM_TOKEN
Content-Type: application/json
```

**Request Body** (success):
```json
{
  "status": "completed",
  "duration_seconds": 25,
  "actual_feed_time": "2026-04-30T06:46:00Z",
  "amount_dispensed": 2.5
}
```

**Expected Response** (200 OK):
```json
{
  "id": 1,
  "feeder_id": 1,
  "hog_pen_id": 1,
  "feed_type": "grower",
  "scheduled_at": "2026-04-30T06:45:32Z",
  "actual_feed_time": "2026-04-30T06:46:00Z",
  "status": "completed",
  "duration_seconds": 25,
  "amount_dispensed": "2.50",
  "error_message": null,
  "created_at": "2026-04-30T06:45:32Z",
  "updated_at": "2026-04-30T06:46:00Z"
}
```

**Test via cURL**:
```bash
curl -X PATCH http://localhost:8000/api/v1/feeding-queue/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed",
    "duration_seconds": 25,
    "actual_feed_time": "2026-04-30T06:46:00Z",
    "amount_dispensed": 2.5
  }'
```

**Error Response** (relay timeout):
```json
{
  "status": "error",
  "duration_seconds": 26,
  "error_message": "Exceeded max duration 25s"
}
```

---

### Scenario 3: Get Relay Configuration (HTTP GET)

Used by ESP32 on startup to cache relay configurations.

**Endpoint**: `GET /api/v1/feeders/{id}/relay-config`

**Path Parameter**: `id = 1`

**Header**:
```
Authorization: Bearer YOUR_SANCTUM_TOKEN
```

**Expected Response** (200 OK):
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

**Test via cURL**:
```bash
curl -X GET http://localhost:8000/api/v1/feeders/1/relay-config \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Scenario 4: List All Jobs (Monitoring/Debugging)

**Endpoint**: `GET /api/v1/feeding-queue`

**Query Parameters**:
- `status`: Filter by status (pending, processing, completed, error)
- `feeder_id`: Filter by feeder
- `date`: Filter by date (YYYY-MM-DD)

**Example**:
```bash
GET /api/v1/feeding-queue?status=pending&feeder_id=1
```

**Expected Response** (200 OK):
```json
{
  "data": [
    {
      "id": 2,
      "feeder_id": 1,
      "hog_pen_id": 1,
      "feed_type": "finisher",
      "scheduled_at": "2026-04-30T07:00:32Z",
      "actual_feed_time": null,
      "status": "pending",
      "duration_seconds": null,
      "amount_dispensed": null,
      "error_message": null,
      "created_at": "2026-04-30T06:45:32Z",
      "updated_at": "2026-04-30T06:45:32Z"
    }
  ],
  "links": { "first": "...", "last": "...", "next": "..." },
  "meta": { "current_page": 1, "from": 1, "path": "...", "per_page": 50, "to": 1, "total": 1 }
}
```

---

## Testing Workflow

### Step 1: Authenticate
```bash
# Generate Sanctum token
php artisan tinker
> $user = App\Models\User::first();
> $token = $user->createToken('esp32-test')->plainTextToken;
> echo $token;  # Copy this
```

### Step 2: Start Laravel Server
```bash
php artisan serve --port=8000
# Server running at http://127.0.0.1:8000
```

### Step 3: Simulate ESP32 Poll Sequence

**Request 1**: Get next job
```bash
curl -X POST http://127.0.0.1:8000/api/v1/feeding-queue/next-job \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"feeder_id": 1, "max_jobs": 1}'

# Response: Job ID 1, relay_pin 14, 25 seconds
```

**[Simulated Hardware]**: Motor runs for 25 seconds on GPIO 14

**Request 2**: Report completion
```bash
curl -X PATCH http://127.0.0.1:8000/api/v1/feeding-queue/1 \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed",
    "duration_seconds": 25,
    "actual_feed_time": "2026-04-30T06:46:00Z",
    "amount_dispensed": 2.5
  }'

# Response: status updated to "completed"
```

**Request 3**: Get next job (now processes finisher)
```bash
curl -X POST http://127.0.0.1:8000/api/v1/feeding-queue/next-job \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"feeder_id": 1, "max_jobs": 1}'

# Response: Job ID 2, relay_pin 27, 30 seconds
```

**Request 4**: Monitor all jobs
```bash
curl http://127.0.0.1:8000/api/v1/feeding-queue \
  -H "Authorization: Bearer TOKEN"

# Response: 2 jobs, one completed, one pending
```

---

## Database Verification

### Check Relay Configuration
```bash
mysql> SELECT * FROM feeder_feed_type_mapping ORDER BY relay_pin;
```

**Expected**:
```
| id | feeder_id | feed_type    | relay_pin | max_duration_seconds | is_active |
|----|-----------|--------------|-----------|----------------------|-----------|
| 1  | 1         | starter      | 12        | 20                   | 1         |
| 2  | 1         | grower       | 14        | 25                   | 1         |
| 4  | 1         | maintenance  | 26        | 15                   | 1         |
| 3  | 1         | finisher     | 27        | 30                   | 1         |
```

### Check Feeding Queue Status
```bash
mysql> SELECT id, feed_type, status, duration_seconds, actual_feed_time FROM feeding_queue ORDER BY id;
```

**After test run**:
```
| id | feed_type | status    | duration_seconds | actual_feed_time    |
|----|-----------|-----------|------------------|---------------------|
| 1  | grower    | completed | 25               | 2026-04-30 06:46:00 |
| 2  | finisher  | pending   | NULL             | NULL                |
```

### Check Feeder-Pen Relationship
```bash
mysql> SELECT f.id, f.hog_pen_id, hp.name, f.status FROM feeders f JOIN hog_pens hp ON f.hog_pen_id=hp.id;
```

---

## Real ESP32 Testing

### Hardware Checklist
- [ ] ESP32 Dev Board
- [ ] 4-Channel Relay Module
- [ ] WiFi connectivity
- [ ] USB cable for serial monitor

### Upload to ESP32
1. Open `ESP32_FEEDING_SYSTEM.ino` in Arduino IDE
2. Update WiFi credentials and API token
3. Select Tools → Board → ESP32 Dev Module
4. Select Tools → Port → COM port
5. Click Upload
6. Open Serial Monitor (115200 baud)

### Expected Serial Output
```
ESP32 Feeding System Starting...
Connecting to WiFi: YOUR_SSID
...................
✓ WiFi Connected!
IP Address: 192.168.1.100

Polling for jobs... ✓ Response received

--- New Feeding Job ---
Job ID: 1
Feed Type: grower
Relay Pin: 14
Max Duration: 25

>>> Executing Feeding Job <<<
Activating relay on GPIO 14
[Motor runs for 25 seconds...]
Relay deactivated
Updating job status to 'completed'... ✓ Status updated
>>> Job Completed <<<
```

---

## Production Deployment

### Authentication
```php
// Generate personal access token for ESP32
$token = $user->createToken(
    'esp32-feeder-' . config('app.env'),
    ['feeding:manage']  // Scoped permissions
)->plainTextToken;

// Store in secure storage / env file
```

### Rate Limiting (nginx)
```nginx
location /api/v1/feeding-queue/ {
    limit_req zone=esp32 burst=10 nodelay;
}

limit_req_zone $binary_remote_addr zone=esp32:10m rate=2r/s;
```

### HTTPS
```php
// In .env
PREDICTION_API_URL=https://api.yourdomain.com/api/v1
// Update ESP32 code to use HTTPS URLs
```

### Database Backups
```bash
# Daily backup of feeding_queue table
mysqldump -u user -p smarthogapi feeding_queue > backup_$(date +%Y%m%d).sql
```

### Monitoring
```php
// Set up alerts for:
// - Jobs pending > 1 hour
// - Relay timeout violations
// - API 500 errors
// - WiFi disconnections
```

---

## Troubleshooting Checklist

| Issue | Cause | Solution |
|-------|-------|----------|
| ESP32 can't connect to WiFi | Wrong SSID/password | Verify WiFi credentials |
| 401 Unauthorized | Invalid/expired token | Regenerate Sanctum token |
| 422 Validation Error | Bad JSON payload | Check field types & names |
| Motor won't stop | Relay stuck open | Check GPIO pin, test relay |
| Jobs piling up | API unreachable | Verify Laravel server running |
| Database full | No cleanup | Run stale job cleanup command |

---

## Next Steps

1. **Upload ESP32 Sketch**: Flash `ESP32_FEEDING_SYSTEM.ino`
2. **Configure WiFi**: Update SSID, password, server URL
3. **Generate Token**: Create Sanctum token for ESP32
4. **Test API**: Run through test scenarios above
5. **Monitor Production**: Set up alerts & logs
6. **Automation**: Integrate with PredictionService for stage transitions
