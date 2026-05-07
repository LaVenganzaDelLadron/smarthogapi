# TODO

## Hog health predictions removal
- [ ] Delete migration `2026_05_07_040154_expand_hog_health_predictions_table.php`
- [ ] Remove model `app/Models/HogHealthPredictions.php`
- [ ] Update `app/Services/PredictionService.php` to stop writing to `hog_health_predictions`
- [ ] Update `app/Services/FastAPIIntegration.php` to stop creating `HogHealthPredictions`
- [ ] Update `routes/api.php` to remove `hog-health-predictions` apiResource route
- [x] Search repo for remaining references to `hog_health_predictions`
- [x] Run `php artisan migrate:fresh --seed` to validate migrations and seeding


