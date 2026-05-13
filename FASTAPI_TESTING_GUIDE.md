# Testing Guide - FastAPI Integration

## 🧪 Quick Test Suite

### 1. Health Check

```bash
# Test FastAPI connectivity
curl http://localhost:8000/api/v1/predictions/health

# Expected: 200 OK
# { "status": "ok", "service": "smart-hog-fastapi-integration" }
```

---

### 2. Single Prediction (Sync)

```bash
# Replace with real pen_id
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_id": 1,
    "use_cache": true
  }'

# Expected: 200 OK
# {
#   "success": true,
#   "prediction_id": 123,
#   "data": { ... }
# }
```

---

### 3. Prediction with Cache (2nd Request)

```bash
# Run same request again - should be instant
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_id": 1}'

# Expected: 200 OK in ~10ms (from cache)
```

---

### 4. Async Prediction

```bash
# Request returns immediately
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_id": 1,
    "async": true
  }'

# Expected: 202 Accepted
# {
#   "success": true,
#   "job_id": "job_12345",
#   "message": "Prediction queued for processing"
# }

# Result will be delivered via webhook (if configured)
```

---

### 5. Batch Prediction

```bash
# Process multiple pens at once
curl -X POST http://localhost:8000/api/v1/predictions/batch/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_ids": [1, 2, 3],
    "async": false
  }'

# Expected: 200 OK
# {
#   "success": true,
#   "count": 3,
#   "data": { ... }
# }
```

---

### 6. Batch Weight Trend

```bash
curl -X POST http://localhost:8000/api/v1/predictions/batch/weight-trend \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_ids": [1, 2, 3, 4, 5]
  }'

# Expected: 200 OK with weight trends for all pens
```

---

### 7. Batch Pen Status

```bash
curl -X POST http://localhost:8000/api/v1/predictions/batch/pen-status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_ids": [1, 2, 3, 4, 5]
  }'

# Expected: 200 OK with status for all pens
```

---

### 8. Model Training (Fast)

```bash
# CLI - Quick validation training
php artisan ml:train fast --epochs=10

# Output: Training completed, shows accuracy
```

---

### 9. Model Training (Full)

```bash
# CLI - Full production training (takes longer)
php artisan ml:train full

# Output: Training completed, saves artifacts
```

---

### 10. Cache Management

```bash
# Check cache in Redis
redis-cli KEYS "prediction:*"

# Clear all caches
php artisan predictions:clear-cache

# Clear only expired
php artisan predictions:clear-cache --expired

# Verify clear
redis-cli KEYS "prediction:*"  # Should be empty
```

---

## 📊 Database Verification

### Check Predictions Stored

```bash
php artisan tinker

# View recent predictions
>>> $preds = FeedingPredictions::latest()->limit(5)->get();
>>> $preds->each(fn($p) => echo "Pen {$p->hog_pen_id}: {$p->confidence_score}\n");

# Check for alerts
>>> FeedingPredictions::where('alerts', '!=', '[]')->get();

# Check cache entries
>>> DB::table('prediction_cache')->get();

# Check webhook logs
>>> DB::table('webhook_logs')->latest()->limit(10)->get();
```

---

## 🔧 Queue Testing

### Start Queue Worker

```bash
# Terminal 1: Start queue worker (listens for jobs)
php artisan queue:work predictions

# Keep this running to process async jobs
```

### Submit Async Job

```bash
# Terminal 2: Submit async request
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_id": 1, "async": true}'

# Watch Terminal 1 for processing
```

### Monitor Queue

```bash
# In another terminal
php artisan tinker
>>> Redis::llen('queues:predictions')  # Jobs in queue
>>> Redis::smembers('queues:predictions')  # View jobs
>>> Artisan::call('queue:failed')  # View failed jobs
```

---

## 🔔 Webhook Testing

### 1. Configure Webhooks

Update `.env`:
```env
FASTAPI_WEBHOOKS=http://localhost:8000/webhook/ml,https://webhook.site/your-uuid
```

### 2. Test Webhook Endpoint

Create a simple test endpoint in `routes/web.php`:

```php
Route::post('/webhook/ml', function (Request $request) {
    Log::info('Webhook received', $request->all());
    return response()->json(['status' => 'ok']);
});
```

### 3. Trigger Event

```bash
# Make prediction - triggers webhook
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_id": 1}'

# Check logs
tail -f storage/logs/laravel.log | grep Webhook
```

### 4. View Webhook Logs

```bash
php artisan tinker
>>> DB::table('webhook_logs')->latest()->limit(10)->get();
>>> DB::table('webhook_logs')->where('status', 'failed')->get();
```

---

## 🧪 Unit Tests

### Run All Tests

```bash
php artisan test
```

### Run Specific Test File

```bash
php artisan test tests/Feature/PredictionTest.php
php artisan test tests/Feature/AsyncPredictionJobTest.php
php artisan test tests/Unit/FastAPIIntegrationTest.php
```

### Run with Coverage

```bash
php artisan test --coverage
php artisan test --coverage --coverage-html=coverage/
```

---

## 📈 Load Testing

### Test Caching Performance

```php
// Tinker script to test cache performance
use App\Services\FastAPIIntegration;
use Illuminate\Support\Facades\Cache;

$fastapi = app(FastAPIIntegration::class);

// First call (no cache)
$start = microtime(true);
$result1 = $fastapi->predictFeedRecommendation(1, [], false, false);
$first = microtime(true) - $start;
echo "First call: {$first}s\n";

// Second call (from cache)
$start = microtime(true);
$result2 = $fastapi->predictFeedRecommendation(1, [], false, true);
$cached = microtime(true) - $start;
echo "Cached call: {$cached}s\n";
echo "Speedup: " . round($first / $cached) . "x faster\n";
```

### Test Batch vs Single

```php
$fastapi = app(FastAPIIntegration::class);

// Single predictions (3x calls)
$start = microtime(true);
for ($i = 1; $i <= 3; $i++) {
    $fastapi->predictFeedRecommendation($i, [], false, false);
}
$single = microtime(true) - $start;

// Batch prediction (1x call)
$start = microtime(true);
$fastapi->batchPredictFeedRecommendation([1, 2, 3], false);
$batch = microtime(true) - $start;

echo "Single x3: {$single}s\n";
echo "Batch: {$batch}s\n";
echo "Speedup: " . round($single / $batch) . "x faster\n";
```

---

## 🔍 Debugging

### Enable Debug Logging

Add to `.env`:
```env
LOG_LEVEL=debug
```

Watch logs:
```bash
tail -f storage/logs/laravel.log
```

### Trace API Calls

```php
// In tinker
$fastapi = app(FastAPIIntegration::class);

// Enable logging
Log::info('Starting prediction');
$result = $fastapi->predictFeedRecommendation(1);
Log::info('Prediction result', $result);
```

### Check Configuration

```php
// Verify FastAPI config
echo config('services.fastapi.url');      // http://localhost:5000
echo config('services.fastapi.timeout');  // 30
print_r(config('services.fastapi.webhooks'));
```

---

## 🐛 Common Issues & Solutions

### Issue: "FastAPI error: HTTP 503"
```
Solution: Ensure FastAPI service is running
$ python -m uvicorn main:app --port 5000
```

### Issue: "No valid pens found" (batch)
```
Solution: Verify pen IDs exist in database
php artisan tinker
>>> Hogpens::pluck('id');
```

### Issue: Async job not processing
```
Solution: Start queue worker
php artisan queue:work predictions
```

### Issue: Webhook not firing
```
Solution: Check configuration and URL
php artisan tinker
>>> config('services.fastapi.webhooks')
```

### Issue: Cache not working
```
Solution: Verify Redis is running
redis-cli ping  # Should return PONG
```

---

## ✅ Verification Checklist

- [ ] FastAPI health check returns 200
- [ ] Single prediction works (200)
- [ ] Cache speeds up 2nd request (~10ms)
- [ ] Async request returns 202
- [ ] Batch requests work (200)
- [ ] New batch endpoints return data
- [ ] Queue worker processes jobs
- [ ] Webhooks are logged
- [ ] Database tables populated
- [ ] No errors in logs
- [ ] Training commands work
- [ ] Cache clear command works

---

## 🎯 Performance Benchmarks

Typical results:

| Operation | Time | Notes |
|-----------|------|-------|
| Health check | 50ms | No cache |
| First prediction | 500-2000ms | Calls FastAPI |
| Cached prediction | 10ms | From Redis |
| Batch of 10 | 500-1000ms | Single FastAPI call |
| Async queue | 100ms | Returns immediately |
| Train (fast) | 2-5s | Quick validation |
| Train (full) | 30-60s | Production ready |

---

## 📞 Debugging Commands

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Check queue status
php artisan queue:work --verbose

# List pending jobs
php artisan queue:pending predictions

# View failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry [id]

# Check database
php artisan tinker
>>> DB::table('webhook_logs')->count()
>>> DB::table('feeding_predictions')->latest()->first()
>>> Cache::get('prediction:feed_recommendation:pen_1')
```

---

## 🚀 Production Checklist

- [ ] FastAPI service is running and healthy
- [ ] Redis cache is configured and working
- [ ] Queue worker is running in background
- [ ] Webhooks are configured for external systems
- [ ] Database migrations are applied
- [ ] Environment variables are set
- [ ] Logs are being written
- [ ] Error monitoring is in place
- [ ] Rate limiting is configured (if needed)
- [ ] Backups are scheduled

---

## 📚 Additional Resources

- See `FASTAPI_ENHANCEMENT_GUIDE.md` for complete API documentation
- See `FASTAPI_IMPLEMENTATION_COMPLETE.md` for technical overview
- See code comments in `app/Services/FastAPIIntegration.php`
- See job configuration in `app/Jobs/AsyncPredictionJob.php`

---

All tests should pass and system should be production-ready! 🎉
