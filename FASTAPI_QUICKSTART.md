# FastAPI Integration - Quick Start Guide

## ✅ What's Been Done

1. **Database Migrations** - ✅ Applied
   - `feeding_predictions` table expanded with 12 new columns
   - `hog_health_predictions` table expanded with 9 new columns
   - All JSON fields for complex data (warnings, alerts, trends, etc.)

2. **Models Updated** - ✅ Ready
   - `FeedingPredictions` with proper JSON casting
   - `HogHealthPredictions` with proper JSON casting
   - Helper methods for accessing data

3. **Service Layer** - ✅ Ready
   - `FastAPIIntegration` service handles all communication
   - Automatic request building from pen/hog data
   - Automatic database storage of predictions

4. **API Endpoints** - ✅ Registered
   - Health check endpoint
   - Feed recommendation endpoint
   - Weight trend endpoint
   - Pen status endpoint
   - Batch prediction endpoint

## 🚀 Getting Started (3 Steps)

### Step 1: Verify FastAPI is Running

```bash
# Check if FastAPI service is running on port 5000
curl http://localhost:5000/health

# Expected response:
# {"status":"ok","service":"smart-hog-fastapi"}
```

If not running, start it:
```bash
# In your ml-service-api directory
uvicorn main:app --host 0.0.0.0 --port 5000
```

### Step 2: Set Environment Variable (Optional)

If FastAPI is running on a different host/port, update `.env`:
```env
FASTAPI_URL=http://localhost:5000
FASTAPI_TIMEOUT=30
```

### Step 3: Test the Health Endpoint

```bash
# Test Laravel can reach FastAPI
curl http://localhost:8000/api/v1/predictions/health

# Expected response (no auth required):
# {"status":"ok","service":"smart-hog-fastapi-integration"}
```

## 💻 Usage Examples

### Example 1: Get Feed Recommendation via API

```bash
# Requires authentication token
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_id": 1
  }'

# Response:
# {
#   "prediction_id": 123,
#   "data": {
#     "feed_recommendation": {...},
#     "weight_trend": [...],
#     "pen_status": {...},
#     "warnings": [...],
#     "suggestions": [...]
#   }
# }
```

### Example 2: Get Weight Trend

```bash
curl -X POST http://localhost:8000/api/v1/predictions/weight-trend \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_id": 1}'
```

### Example 3: Batch Predictions

```bash
curl -X POST http://localhost:8000/api/v1/predictions/batch/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pen_ids": [1, 2, 3]
  }'
```

### Example 4: Query Results from Laravel

```php
// In a controller or service
use App\Models\FeedingPredictions;

// Get latest prediction for a pen
$prediction = FeedingPredictions::where('hog_pen_id', 1)
    ->latest()
    ->first();

// Access the data (automatically cast from JSON)
$recommendation = $prediction->feed_recommendation;
$suggested_feed = $recommendation['recommended_feed_per_pig_per_day'];
$confidence = $recommendation['confidence_score'];

// Check for warnings
if ($prediction->hasWarnings()) {
    foreach ($prediction->warnings as $warning) {
        logger()->warning("Feed prediction warning: $warning");
    }
}

// Check for suggestions
foreach ($prediction->suggestions as $suggestion) {
    echo $suggestion; // "Reduce feed by 10% for better conversion"
}
```

## 📊 Data Flow

```
User Request
    ↓
Laravel Route (/api/v1/predictions/feed-recommendation)
    ↓
PredictionController (validates, accepts request)
    ↓
FastAPIIntegration Service (builds payload from pen data)
    ↓
HTTP POST to FastAPI (http://localhost:5000/predict/feed-recommendation)
    ↓
FastAPI ML Service (runs model inference)
    ↓
Returns FeedResponse with full prediction + warnings/alerts/suggestions
    ↓
Service stores in database (FeedingPredictions model)
    ↓
Returns prediction_id + data to client
    ↓
Client receives: {"prediction_id": 123, "data": {...}}
```

## 🔍 Monitoring

### Check Prediction Health via Logs

```bash
# Watch Laravel logs for prediction activity
tail -f storage/logs/laravel.log | grep -i prediction

# You'll see entries like:
# [2026-05-07 11:41:20] local.INFO: Calling FastAPI feed recommendation for pen 1
# [2026-05-07 11:41:22] local.INFO: Feed prediction stored for pen 1 {"prediction_id":123,"confidence_score":0.85}
```

### Query Database

```bash
# See latest predictions
SELECT id, hog_pen_id, predicted_feed_amount, confidence_level, created_at 
FROM feeding_predictions 
ORDER BY created_at DESC 
LIMIT 10;

# See predictions with warnings
SELECT id, predicted_feed_amount, warnings, created_at 
FROM feeding_predictions 
WHERE warnings IS NOT NULL AND warnings != '[]'
ORDER BY created_at DESC;
```

## 🐛 Troubleshooting

### "FastAPI service unavailable" Error

**Check 1:** Is FastAPI running?
```bash
ps aux | grep uvicorn
curl -v http://localhost:5000/health
```

**Check 2:** Is the URL correct in `.env`?
```bash
php artisan config:show services.fastapi.url
```

**Check 3:** Check Laravel logs
```bash
tail -20 storage/logs/laravel.log
```

### "pen_id must exist" Validation Error

Make sure the pen exists:
```php
// In Tinker
>>> App\Models\Hogpens::find(1)
// Should return a pen object, not null
```

### Null Warnings/Alerts/Suggestions

These are only populated if FastAPI returns them. Check the full response:
```php
>>> $prediction = App\Models\FeedingPredictions::find(123)
>>> dd($prediction->fastapi_response)
```

## 📈 Next Steps

1. **Create a Dashboard**
   - Display latest predictions for each pen
   - Show alerts/warnings prominently
   - Track trends over time

2. **Scheduled Predictions**
   - Add Laravel Scheduler job to predict regularly
   - Example: Every hour, predict for all pens
   ```php
   // app/Console/Kernel.php
   $schedule->call(function () {
       $pens = \App\Models\Hogpens::all();
       foreach ($pens as $pen) {
           app(\App\Services\FastAPIIntegration::class)
               ->predictFeedRecommendation($pen->id);
       }
   })->hourly();
   ```

3. **Alert System**
   - Send notifications when alerts are triggered
   - Notify farm manager of warnings
   ```php
   if ($prediction->hasAlerts()) {
       \App\Notifications\PredictionAlertNotification::dispatch($prediction);
   }
   ```

4. **Integration with Feeding Queue**
   - Auto-populate feeding queue based on predictions
   - Adjust feed amounts based on recommendations

## 📚 Documentation

- Full details: See `FASTAPI_INTEGRATION.md`
- Implementation report: See `MIGRATION_VERIFICATION_REPORT.md`
- API schema: Based on FastAPI documentation provided

## ✅ Checklist Before Going Live

- [ ] FastAPI service tested and running
- [ ] `.env` has correct `FASTAPI_URL`
- [ ] Health check endpoint responds successfully
- [ ] At least one prediction created successfully
- [ ] Warnings/alerts stored correctly in database
- [ ] Staff trained on using prediction endpoints
- [ ] Monitoring/alerts set up for failures
- [ ] Database backups configured
- [ ] Test with actual pen data

## 💡 Tips

1. **Override Pen Data** - You can send overrides in the request body:
   ```bash
   curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
     -H "Authorization: Bearer TOKEN" \
     -d '{
       "pen_id": 1,
       "pig_age_days": 35,
       "avg_weight_kg": 30.0
     }'
   ```

2. **Batch for Efficiency** - Predict multiple pens at once:
   ```bash
   # Faster than individual requests
   curl -X POST http://localhost:8000/api/v1/predictions/batch/feed-recommendation \
     -d '{"pen_ids": [1, 2, 3, 4, 5]}'
   ```

3. **Check Confidence** - Always monitor confidence levels:
   - `high` = Trust the prediction (0.8+)
   - `medium` = Use with caution (0.6-0.8)
   - `low` = Verify manually (<0.6)

4. **Review Suggestions** - The AI provides actionable suggestions:
   ```php
   foreach ($prediction->suggestions as $suggestion) {
       echo "💡 " . $suggestion; // e.g., "Reduce feed by 10%"
   }
   ```

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check FastAPI logs: FastAPI service console
3. Review `FASTAPI_INTEGRATION.md` for detailed API documentation
4. Test individual endpoints to isolate issues

---

**Ready to go!** 🎉

Start with Step 1 (verify FastAPI) → Step 2 (set env) → Step 3 (test health endpoint) → Done!
