# FastAPI Integration Guide - SmartHog v2

## Overview

Your Laravel application (`smarthogapi`) now has a complete FastAPI integration layer that connects to the FastAPI ML service running on port 5000. This guide explains what was implemented and how to use it.

## What Was Implemented

### 1. Database Migrations (✅ Applied)

Two migrations were created to expand your prediction tables with FastAPI response fields:

#### **2026_05_07_040149_expand_feeding_predictions_table.php**
Adds to `feeding_predictions` table:
- `model_used` - ML model name used for prediction
- `confidence_level` - High/Medium/Low confidence rating
- `confidence_reason` - Text explanation of confidence
- `feed_recommendation` - Full recommendation object (JSON)
- `feed_totals` - Daily/weekly totals (JSON)
- `weight_trend` - Array of predicted weight rows (JSON)
- `pen_status` - Predicted pen status (JSON)
- `warnings` - Array of warning codes/messages
- `alerts` - Array of alert codes/messages
- `suggestions` - Array of human-readable suggestions
- `fastapi_response` - Full API response for audit (JSON)
- `predicted_at` - Timestamp of prediction

#### **2026_05_07_040154_expand_hog_health_predictions_table.php**
Adds to `hog_health_predictions` table:
- Similar fields as above for health/weight/pen-status predictions
- `weight_trend` - Array of weight prediction rows
- `pen_status` - Pen status classification
- `metrics` - Model performance metrics (JSON)

### 2. Updated Models

#### **FeedingPredictions Model** (`app/Models/FeedingPredictions.php`)
```php
// Automatically casts JSON columns to arrays
protected $casts = [
    'feed_recommendation' => 'array',
    'weight_trend' => 'array',
    'pen_status' => 'array',
    'warnings' => 'array',
    'alerts' => 'array',
    'suggestions' => 'array',
    'fastapi_response' => 'array',
];

// Relationships
public function hogPen() // belongs to hog pen
public function mlModel() // belongs to ML model

// Accessors
$prediction->recommended_feed // Get recommended amount
$prediction->hasWarnings() // Check for warnings
$prediction->hasAlerts() // Check for alerts
```

#### **HogHealthPredictions Model** (`app/Models/HogHealthPredictions.php`)
```php
// Similar JSON casting as above
protected $casts = [
    'weight_trend' => 'array',
    'pen_status' => 'array',
    'warnings' => 'array',
    'metrics' => 'array',
    'fastapi_response' => 'array',
];

// Helper methods
$prediction->hasWeightTrend() // Check for weight data
$prediction->getLatestWeightPrediction() // Get last predicted row
$prediction->hasWarnings() // Check for warnings
```

### 3. FastAPI Integration Service

**File:** `app/Services/FastAPIIntegration.php`

This service handles all communication with the FastAPI ML service:

```php
// Initialize (auto-injected via Laravel DI)
$service = new FastAPIIntegration();

// Feed Recommendation
$result = $service->predictFeedRecommendation($penId, $overrides);
// Returns: ['success' => true, 'prediction_id' => 123, 'data' => {...}]

// Weight Trend Prediction
$result = $service->predictWeightTrend($penId, $overrides);

// Pen Status Classification
$result = $service->predictPenStatus($penId, $overrides);

// Batch Predict Multiple Pens
$result = $service->batchPredictFeedRecommendation($penIds);

// Health Check
$isHealthy = $service->healthCheck(); // bool
```

**Key Features:**
- Automatic request payload building from pen/hog data
- Automatic database storage of predictions
- Error handling and logging
- Health check caching (5 minutes)
- Timeout protection (30 seconds default)

### 4. API Controller & Routes

**File:** `app/Http/Controllers/Api/PredictionController.php`

**Registered Routes:**

```
# Health Check (no auth required)
GET /api/v1/predictions/health
Response: {"status":"ok","service":"smart-hog-fastapi-integration"}

# Feed Recommendation
POST /api/v1/predictions/feed-recommendation
Request: {"pen_id": 1, ...overrides}
Response: {"prediction_id": 123, "data": {...FastAPI response...}}

# Weight Trend
POST /api/v1/predictions/weight-trend
Request: {"pen_id": 1, ...overrides}

# Pen Status Classification
POST /api/v1/predictions/pen-status
Request: {"pen_id": 1, ...overrides}

# Batch Feed Recommendation
POST /api/v1/predictions/batch/feed-recommendation
Request: {"pen_ids": [1, 2, 3]}
Response: {"count": 3, "data": {...results...}}
```

### 5. Configuration

**File:** `config/services.php`

```php
'fastapi' => [
    'url' => env('FASTAPI_URL', 'http://localhost:5000'),
    'timeout' => env('FASTAPI_TIMEOUT', 30),
],
```

**Environment Variables** (add to `.env`):
```env
FASTAPI_URL=http://localhost:5000
FASTAPI_TIMEOUT=30
```

## Usage Examples

### Example 1: Get Feed Recommendation for a Pen

```php
use App\Services\FastAPIIntegration;

// Inject via constructor
public function __construct(private FastAPIIntegration $fastapi) {}

// Get prediction
$result = $this->fastapi->predictFeedRecommendation(1);

if ($result['success']) {
    // Access stored prediction
    $prediction = FeedingPredictions::find($result['prediction_id']);
    
    // Data is automatically cast to arrays
    $recommendation = $prediction->feed_recommendation;
    $suggested_amount = $recommendation['recommended_feed_per_pig_per_day'];
    $confidence = $recommendation['confidence_score'];
    $warnings = $prediction->warnings; // array
    $alerts = $prediction->alerts; // array
}
```

### Example 2: API Usage via HTTP

```bash
# Health Check
curl http://localhost:8000/api/v1/predictions/health

# Feed Recommendation
curl -X POST http://localhost:8000/api/v1/predictions/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_id": 1}'

# Batch Predictions
curl -X POST http://localhost:8000/api/v1/predictions/batch/feed-recommendation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pen_ids": [1, 2, 3]}'
```

### Example 3: Store & Query Predictions

```php
use App\Models\FeedingPredictions;

// Query latest predictions for a pen
$latest = FeedingPredictions::where('hog_pen_id', 1)
    ->latest()
    ->first();

// Check for warnings/alerts
if ($latest->hasWarnings()) {
    Log::warning("Prediction has warnings", $latest->warnings);
}

if ($latest->hasAlerts()) {
    Log::alert("Prediction has alerts", $latest->alerts);
}

// Access suggestions
foreach ($latest->suggestions as $suggestion) {
    echo $suggestion; // Human-readable guidance
}
```

## FastAPI Request/Response Mapping

### Feed Recommendation Request

```php
[
    'pig_age_days' => 30,
    'avg_weight_kg' => 25.5,
    'growth_stage' => 'grower',
    'current_feed_kg' => 2.1,
    'pen_capacity' => 20,
    'device_code' => 'ESP32_001',
    'feeding_times' => ['08:00', '12:00', '16:00'], // exactly 3
    'num_pens' => 4,
    'feed_type' => 'grower_diet'
]
```

### FastAPI Response Structure

```php
[
    'input' => [...normalized input...],
    'feed_recommendation' => [
        'recommended_feed_per_pig_per_day' => 2.5,
        'confidence_score' => 0.85,
        'adjustment_factor' => 1.1,
        'min_feed_kg' => 2.0,
        'max_feed_kg' => 3.0,
    ],
    'feed_totals' => [
        'per_pig_daily_kg' => 2.5,
        'per_pen_daily_kg' => 50.0,
        'per_pen_weekly_kg' => 350.0,
    ],
    'weight_trend' => [
        ['age_days' => 30, 'predicted_weight_kg' => 25.5, 'daily_gain_kg' => 0.75],
        ['age_days' => 31, 'predicted_weight_kg' => 26.2, 'daily_gain_kg' => 0.75],
        // ... more rows
    ],
    'pen_status' => [
        'status' => 'healthy',
        'confidence_score' => 0.92,
        'probabilities' => ['healthy' => 0.92, 'stressed' => 0.05, 'sick' => 0.03]
    ],
    'warnings' => ['OUT_OF_DISTRIBUTION', 'FEED_OVERLIMIT'],
    'alerts' => [],
    'suggestions' => [
        'Reduce feed by 10% to optimize conversion',
        'Monitor weight gain - trending below average'
    ],
    'confidence_level' => 'high',
    'confidence_reason' => 'Model trained on 50k+ similar hogs',
    'model_used' => 'ensemble_v2',
]
```

## Error Handling

### FastAPI Service Down

When FastAPI is unavailable:
- Returns 400 status with error message
- Details logged to Laravel logs
- Cache used if available (for health/weight/pen-status)

```php
$result = $this->fastapi->predictFeedRecommendation(1);

if (!$result['success']) {
    // Handle error
    echo "Prediction failed: " . $result['error'];
}
```

### Validation Errors

FastAPI validates input constraints:
- `pig_age_days` >= 0
- `avg_weight_kg` > 0
- `feeding_times` exactly length 3
- `pen_capacity` >= 1
- `num_pens` >= 1

Laravel controller validates:
- `pen_id` must exist in database
- Batch requests need at least 1 pen

## Database Schema

### feeding_predictions Table

```sql
CREATE TABLE feeding_predictions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hog_pen_id BIGINT NOT NULL REFERENCES hog_pens(id),
    ml_model_id BIGINT REFERENCES ml_models(id),
    predicted_feed_amount DECIMAL(8,2),          -- Primary recommendation amount
    confidence_score DECIMAL(8,2),
    model_used VARCHAR(255),                      -- Model name
    confidence_level VARCHAR(50),                 -- 'high', 'medium', 'low'
    confidence_reason TEXT,
    feed_recommendation JSON,
    feed_totals JSON,
    weight_trend JSON,
    pen_status JSON,
    warnings JSON,
    alerts JSON,
    suggestions JSON,
    fastapi_response JSON,                        -- Full response audit trail
    predicted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX(hog_pen_id),
    INDEX(ml_model_id),
    INDEX(predicted_at)
);
```

### hog_health_predictions Table

```sql
CREATE TABLE hog_health_predictions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hog_id BIGINT NOT NULL REFERENCES hogs(id),
    ml_model_id BIGINT REFERENCES ml_models(id),
    predicted_status VARCHAR(255),                -- 'weight_trending', 'healthy', etc.
    risk_score DECIMAL(8,2),                      -- 0-100
    model_used VARCHAR(255),
    confidence_level VARCHAR(50),
    confidence_reason TEXT,
    weight_trend JSON,
    pen_status JSON,
    warnings JSON,
    metrics JSON,
    fastapi_response JSON,
    predicted_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX(hog_id),
    INDEX(ml_model_id),
    INDEX(predicted_at)
);
```

## Testing

### Run Migrations First

```bash
php artisan migrate
```

### Test Health Check

```bash
# Laravel API
curl http://localhost:8000/api/v1/predictions/health

# Or in Tinker
>>> app(App\Services\FastAPIIntegration::class)->healthCheck()
// Returns: true|false
```

### Test Feed Prediction

```php
// In Tinker
>>> $service = app(App\Services\FastAPIIntegration::class)
>>> $result = $service->predictFeedRecommendation(1)
>>> dd($result)
```

## Troubleshooting

### "FastAPI service unavailable"

1. Check if FastAPI is running:
   ```bash
   curl http://localhost:5000/health
   ```

2. Verify configuration:
   ```bash
   php artisan config:show services.fastapi
   ```

3. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### "Unmatched '}'  in migrations"

Migrations use proper Laravel schema. Run:
```bash
php artisan migrate --step
```

### Database Errors

Ensure MySQL/MariaDB supports JSON columns (5.7+):
```sql
SELECT VERSION();  -- Should be 5.7.8 or higher
```

## Next Steps

1. **Test the API** - Use the health check endpoint first
2. **Monitor logs** - Watch `storage/logs/laravel.log` for issues
3. **Schedule predictions** - Consider adding scheduled jobs:
   ```php
   // app/Console/Kernel.php
   $schedule->call(function () {
       $pens = Hogpens::all();
       foreach ($pens as $pen) {
           app(FastAPIIntegration::class)->predictFeedRecommendation($pen->id);
       }
   })->hourly();
   ```
4. **API Dashboard** - Build UI to display prediction results
5. **Alert System** - Trigger notifications when alerts/warnings appear

## Configuration Checklist

- [ ] FastAPI service is running on `http://localhost:5000`
- [ ] `.env` has `FASTAPI_URL` set (or using default)
- [ ] Migrations have been run: `php artisan migrate`
- [ ] Routes are registered: `php artisan route:list | grep predictions`
- [ ] Models are imported in controllers
- [ ] Tests pass: `php artisan test`
