#!/usr/bin/env bash

# SmartHog AI Prediction Commands Reference
# =========================================
# All prediction commands work with --all (batch) or --id (single item)

# 1. HOG HEALTH PREDICTIONS
# ========================

# Predict health for all hogs
/usr/bin/php artisan predict:hog-health --all

# Predict health for specific hog
/usr/bin/php artisan predict:hog-health --hog-id=1

# Output: Health status (healthy/at_risk/sick), risk_score, confidence


# 2. FEED DEMAND FORECASTING
# ==========================

# Forecast feed for all farms
/usr/bin/php artisan predict:feed-demand --all

# Forecast feed for specific farm
/usr/bin/php artisan predict:feed-demand --farm-id=1

# Output: Tomorrow's kg, weekly kg, forecast confidence


# 3. WEIGHT GROWTH PROJECTION
# ============================

# Project growth for all hogs
/usr/bin/php artisan predict:weight-growth --all

# Project growth for specific hog
/usr/bin/php artisan predict:weight-growth --hog-id=1

# Output: Current weight, 7-day projection, 30-day projection, daily growth rate


# 4. DISEASE OUTBREAK RISK
# ========================

# Assess outbreak risk for all pens
/usr/bin/php artisan predict:outbreak-risk --all

# Assess outbreak risk for specific pen
/usr/bin/php artisan predict:outbreak-risk --pen-id=1

# Output: Risk level (LOW/MEDIUM/HIGH), risk score, affected hogs, recommendations


# 5. PREDICTIVE MAINTENANCE
# ==========================

# Assess maintenance for all devices
/usr/bin/php artisan predict:device-risk --all

# Assess maintenance for specific device
/usr/bin/php artisan predict:device-risk --device-id=1

# Output: Status (Normal/Warning/Critical), days until failure, maintenance recommendations


# SCHEDULING EXAMPLE (in app/Console/Kernel.php)
# ================================================

/*
protected function schedule(Schedule $schedule)
{
    // Daily health predictions at 2 AM
    $schedule->command('predict:hog-health --all')->daily()->at('02:00');

    // Feed demand forecast daily at 1 AM
    $schedule->command('predict:feed-demand --all')->daily()->at('01:00');

    // Weight growth projections weekly
    $schedule->command('predict:weight-growth --all')->weekly();

    // Outbreak risk assessments 3 times daily
    $schedule->command('predict:outbreak-risk --all')->everyFourHours();

    // Device maintenance checks daily
    $schedule->command('predict:device-risk --all')->daily()->at('03:00');
}
*/


# REDIS CACHING
# ==============
# All predictions are cached in Redis DB 1 with 24-hour TTL
# Cache keys:
#   - hog_prediction_{hog_id}
#   - feed_demand_{farm_id}
#   - weight_growth_{hog_id}
#   - outbreak_risk_{pen_id}
#   - device_risk_{device_id}
#   - ml_service_health_status (5-minute TTL)


# DOCKER EXAMPLE
# ==============

# Start FastAPI ML service
docker run -d \
  -e DB_HOST=smarthog-db \
  -e DB_USER=smarthog \
  -e DB_PASSWORD=glitcher \
  -e DB_NAME=smarthogapi \
  -p 5000:5000 \
  --network smarthog-net \
  --name ml-service \
  smarthog-ml:latest

# Run predictions from container
docker exec -it smarthog /usr/bin/php artisan predict:hog-health --all
