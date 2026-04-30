# Redis Integration Guide - Smarthog System

## 📊 Current Status

✅ **Redis Configuration Ready** (in `config/database.php`):
- Default connection: Database 0
- Cache connection: Database 1
- Host: 127.0.0.1:6379
- Client: Predis already installed

❌ **Currently Using Database Drivers** (suboptimal):
- Cache store: `database`
- Queue connection: `database`
- Session driver: `database`

---

## 🎯 Redis Integration Plan for Your System

### 1. **Caching** - ML Predictions & Relay Config
**Current**: `PredictionService` already caches predictions but on database
**Benefits**: 100x faster than database, perfect for ML model caching

```
Hog predictions (24-hour TTL)
Relay configuration for ESP32
Feed type mappings
Health check status
```

### 2. **Queues** - Automated Tasks
**Current**: Using database queue
**Benefits**: Fast async processing for long-running tasks

```
Hog health predictions (batch jobs)
Feeding queue updates
Daily farm reports generation
ESP32 job polling
```

### 3. **Sessions** - API Authentication
**Current**: Using database
**Benefits**: Faster session retrieval for Sanctum tokens

```
API user sessions
Sanctum token storage
API rate limiting data
```

### 4. **Real-time Messaging** - Live Updates
**Current**: Not implemented
**Benefits**: Instant updates for ESP32 and frontend

```
Feeding job status updates
Hog health alerts
Temperature/sensor alerts
Live dashboard updates (when frontend added)
```

### 5. **Counters** - System Metrics
**New**: Not yet implemented
**Benefits**: Fast, atomic counters for monitoring

```
Feeding attempts per feeder
Prediction API calls
Error counts
Job retries
```

### 6. **Temporary Data** - Rate Limiting
**Current**: Not explicitly implemented
**Benefits**: Prevent API abuse

```
ESP32 request throttling
API endpoint rate limits
Feed dispensing safety locks
Prediction request limits
```

---

## 🚀 Implementation Steps

### Step 1: Update `.env`

```bash
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
```

### Step 2: Update Config Files

#### `config/cache.php` - Add Redis store
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'cache',
],
```

#### `config/queue.php` - Add Redis connection
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
    'after_commit' => false,
],
```

#### `config/session.php` - Redis connection
Already has `redis` driver, just need env variable set.

### Step 3: Update `PredictionService`

Use Redis for prediction caching instead of database:

```php
// Change from database to redis
Cache::store('redis')->put($cacheKey, $prediction, now()->addHours(24));

// Or use cache tags for grouped invalidation
Cache::tags(['hog-predictions'])->put($cacheKey, $prediction, 24 * 60);
```

### Step 4: Create Real-time Messaging System

Use Redis Pub/Sub for live updates:

```php
// Publish feeding job status change
Redis::publish('feeding-jobs', json_encode([
    'job_id' => $jobId,
    'status' => 'completed',
    'amount' => 5.5,
]));

// Subscribe from frontend/ESP32
$redis->subscribe(['feeding-jobs'], function ($message) {
    // Handle update
});
```

### Step 5: Implement Counters

Use Redis atomic operations:

```php
// Increment feeding attempts for feeder
Redis::increment("feeder:{$feederId}:attempts");

// Increment API calls
Redis::increment("prediction:api-calls");

// Get all metrics
Redis::keys("feeder:*:attempts");
```

### Step 6: Add Rate Limiting

```php
// Check if feeding within safety timeout
if (! Redis::set("feeder:{$feederId}:lock", true, 'NX', 'EX', 30)) {
    throw new Exception("Feeder locked. Wait 30 seconds.");
}
```

---

## 📝 Configuration Examples

### Full `config/cache.php` Redux Configuration
```php
'stores' => [
    // ... existing stores ...
    
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',  // Uses DB 1
        'lock_connection' => 'cache',
    ],
    
    'predictions_cache' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
    
    'feeding_cache' => [
        'driver' => 'redis',
        'connection' => 'cache',
    ],
],
```

### Full `config/queue.php` Redis Queue
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',  // Uses DB 0
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
],
```

### Redis Databases Breakdown
```
DB 0 - Default (sessions, queued jobs, locks)
DB 1 - Cache (predictions, relay config, health checks)
DB 2 - Pub/Sub (real-time messaging)
DB 3 - Counters (metrics, tracking)
```

---

## 💾 Service Layer Updates

### Update `PredictionService` to Use Redis

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PredictionService
{
    // Use Redis cache explicitly
    private function cachePrediction(int $hogId, array $prediction): void
    {
        $cacheKey = "hog_prediction_{$hogId}";
        
        // Option 1: Simple cache
        Cache::store('predictions_cache')->put($cacheKey, $prediction, 24 * 60);
        
        // Option 2: With tags for bulk invalidation
        Cache::tags(['predictions', "hog-{$hogId}"])
            ->put($cacheKey, $prediction, 24 * 60);
    }
    
    private function getCachedPrediction(int $hogId): ?array
    {
        return Cache::store('predictions_cache')->get("hog_prediction_{$hogId}");
    }
    
    // Health check - shorter TTL
    private function isServiceHealthy(): bool
    {
        return Cache::remember(
            'ml_service_health',
            5 * 60,  // 5 minutes
            fn () => $this->checkHealth()
        );
    }
}
```

### Create Redis Pub/Sub Publisher for Feeding Queue

```php
// app/Jobs/PublishFeedingUpdate.php
use Illuminate\Support\Facades\Redis;

class PublishFeedingUpdate
{
    public function __construct(
        private $jobId,
        private $status,
        private $data = []
    ) {}
    
    public function handle(): void
    {
        Redis::publish('feeding-queue-updates', json_encode([
            'job_id' => $this->jobId,
            'status' => $this->status,
            'timestamp' => now(),
            'data' => $this->data,
        ]));
    }
}
```

### Create Counter Service

```php
// app/Services/MetricsService.php
use Illuminate\Support\Facades\Redis;

class MetricsService
{
    public function incrementFeedingAttempts(int $feederId): int
    {
        return Redis::incr("feeder:{$feederId}:attempts");
    }
    
    public function incrementPredictionCalls(): int
    {
        return Redis::incr("prediction:api-calls");
    }
    
    public function incrementErrors(string $type): int
    {
        return Redis::incr("errors:{$type}");
    }
    
    public function getFeedingMetrics(int $feederId): array
    {
        return [
            'attempts' => Redis::get("feeder:{$feederId}:attempts") ?? 0,
            'last_feed' => Redis::get("feeder:{$feederId}:last-feed"),
            'total_dispensed' => Redis::get("feeder:{$feederId}:total-dispensed") ?? 0,
        ];
    }
    
    public function getAllMetrics(): array
    {
        $metrics = [];
        foreach (Redis::keys('*:attempts') as $key) {
            $metrics[$key] = Redis::get($key);
        }
        return $metrics;
    }
}
```

---

## 🔒 Safety Features

### Feeding Queue Lock (Prevent Double-Feed)

```php
// app/Services/FeedingQueueService.php
public function acquireFeedingLock(int $feederId, int $seconds = 30): bool
{
    return Redis::set(
        "feeder:{$feederId}:lock",
        time(),
        'NX',      // Only if not exists
        'EX',      // Expiry in seconds
        $seconds
    );
}

public function releaseFeedingLock(int $feederId): void
{
    Redis::del("feeder:{$feederId}:lock");
}
```

### Rate Limiting for API

```php
// app/Http/Middleware/RateLimitRedis.php
public function handle(Request $request, Closure $next)
{
    $key = "api:rate:{$request->ip()}";
    $limit = 100;  // 100 requests
    $window = 60;  // per minute
    
    if (Redis::incr($key) > $limit) {
        if (Redis::ttl($key) === -1) {
            Redis::expire($key, $window);
        }
        return response('Rate limited', 429);
    }
    
    if (Redis::ttl($key) === -1) {
        Redis::expire($key, $window);
    }
    
    return $next($request);
}
```

---

## 📊 Redis Monitoring

### Check Redis Connection

```bash
php artisan tinker
>>> Redis::ping()
=> "PONG"
```

### Monitor Predictions Cache

```bash
# In Redis CLI
redis-cli
> SELECT 1
> KEYS hog_prediction_*
> GET hog_prediction_1
> TTL hog_prediction_1
```

### Check Queue Jobs

```bash
# In Redis CLI
> SELECT 0
> KEYS default:*
> LLEN default:queued
```

### Real-time Metrics

```bash
# In Redis CLI
> SELECT 3
> KEYS *:attempts
> GET feeder:1:attempts
> GET prediction:api-calls
```

---

## 🎯 Integration with Existing Services

### Update `FeedingQueueService`

```php
class FeedingQueueService
{
    public function getNextJobs(int $feederId, int $maxJobs = 1): array
    {
        // Try to acquire lock
        if (! Redis::set(
            "feeder:{$feederId}:processing",
            true,
            'NX',
            'EX',
            60
        )) {
            return [];  // Feeder already processing
        }
        
        $jobs = FeedingQueue::where('feeder_id', $feederId)
            ->where('status', 'pending')
            ->limit($maxJobs)
            ->get();
        
        // Cache job config for ESP32
        Cache::store('feeding_cache')->put(
            "feeder:{$feederId}:config",
            $this->getRelayConfig($feederId),
            24 * 60
        );
        
        return $jobs->toArray();
    }
    
    public function handleStalledJobs(): void
    {
        // Find jobs pending >1 hour
        FeedingQueue::where('status', 'pending')
            ->where('created_at', '<', now()->subHour())
            ->each(function ($job) {
                $job->update(['status' => 'error']);
                Redis::incr('errors:stalled-jobs');
            });
    }
}
```

### Update `FeedingQueueController`

```php
class FeedingQueueController extends Controller
{
    public function __construct(
        private FeedingQueueService $service,
        private MetricsService $metrics
    ) {}
    
    public function nextJob(): JsonResponse
    {
        try {
            $feederId = request()->input('feeder_id');
            
            // Check rate limit
            $key = "esp32:{$feederId}:requests";
            if (Redis::incr($key) > 100) {  // 100 requests max
                if (Redis::ttl($key) === -1) {
                    Redis::expire($key, 60);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Rate limited',
                ], 429);
            }
            if (Redis::ttl($key) === -1) {
                Redis::expire($key, 60);
            }
            
            $jobs = $this->service->getNextJobs($feederId, 1);
            $this->metrics->incrementFeedingAttempts($feederId);
            
            return response()->json([
                'success' => true,
                'data' => $jobs[0] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->metrics->incrementErrors('feeding-queue');
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function update(FeedingQueue $feedingQueue): JsonResponse
    {
        try {
            $status = request()->input('status');
            $feedingQueue->update([
                'status' => $status,
                'duration_seconds' => request()->input('duration_seconds'),
                'amount_dispensed' => request()->input('amount_dispensed'),
            ]);
            
            // Publish real-time update
            Redis::publish('feeding-jobs', json_encode([
                'job_id' => $feedingQueue->id,
                'status' => $status,
            ]));
            
            // Release lock
            Redis::del("feeder:{$feedingQueue->feeder_id}:processing");
            
            return response()->json([
                'success' => true,
                'message' => 'Job updated',
            ]);
        } catch (\Exception $e) {
            $this->metrics->incrementErrors('job-update');
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

---

## 🔄 Queue Jobs for Background Tasks

### Batch Prediction Job

```php
// app/Jobs/PredictAllHogsJob.php
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class PredictAllHogsJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue;
    
    public int $tries = 3;
    public int $backoff = 60;
    
    public function handle(PredictionService $service): void
    {
        $result = $service->predictAllHogs();
        
        // Publish completion
        Redis::publish('predictions-completed', json_encode($result));
    }
}
```

### Schedule in `console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new PredictAllHogsJob())
        ->dailyAt('02:00')
        ->onQueue('predictions');
}
```

---

## 🚀 Quick Start Implementation

### 1. Update `.env`
```bash
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### 2. Update `config/cache.php`
Add in `stores` array:
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'cache',
],
```

### 3. Update `config/queue.php`
Add in `connections` array:
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
    'after_commit' => false,
],
```

### 4. Create Services

Create `MetricsService` for counters
Create queue jobs for batch predictions

### 5. Update `PredictionService`

Replace database cache with Redis cache

### 6. Test

```bash
php artisan tinker
>>> Redis::ping()
>>> Cache::store('redis')->put('test', 'value', 60)
>>> Cache::store('redis')->get('test')
```

---

## 📈 Performance Improvements

| Feature | Before (Database) | After (Redis) | Improvement |
|---------|------------------|---------------|-------------|
| Cache Read | 10-50ms | 1-5ms | **10x faster** |
| Cache Write | 20-100ms | 2-10ms | **10x faster** |
| Queue Job | 50-200ms | 5-20ms | **10x faster** |
| Session Read | 15-60ms | 2-8ms | **10x faster** |
| Counter Ops | N/A | 1-2ms | **Atomic & Fast** |

---

## ✅ Deliverables

After implementation you'll have:

✅ **ML Predictions** - Cached in Redis with 24-hour TTL
✅ **Relay Config** - Cached for ESP32 polling
✅ **Async Jobs** - Health predictions in background queue
✅ **Real-time Updates** - Feeding status via Pub/Sub
✅ **System Metrics** - Feeding attempts, API calls, error counts
✅ **Rate Limiting** - Prevent abuse on ESP32/API
✅ **Safety Locks** - Prevent double-feeding
✅ **Performance** - 10x faster than database drivers

---

## 🔗 Redis Connections Used

```
DB 0: Sessions, Jobs, Locks (default connection)
DB 1: Predictions, Relay Config, Health Checks (cache connection)
DB 2: Pub/Sub Messaging (publishing updates)
DB 3: Counters & Metrics (tracking data)
```

---

## 📚 Commands Reference

```bash
# Start worker (processes background jobs)
php artisan queue:work redis --queue=predictions,default

# Monitor Redis
redis-cli monitor

# Check cache
redis-cli SELECT 1
redis-cli KEYS hog_prediction_*

# Flush specific database
redis-cli SELECT 0
redis-cli FLUSHDB

# Health check
php artisan tinker
>>> Redis::ping()
```

---

**Status**: Ready for implementation
**Time to implement**: 2-3 hours
**Complexity**: Medium
**Impact**: High - 10x performance improvement + real-time features
