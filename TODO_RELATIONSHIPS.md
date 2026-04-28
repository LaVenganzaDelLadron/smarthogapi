# TODO: Add Eloquent Relationships to Models

All $fillable complete. Now adding belongsTo/hasMany based on user's provided FK refs.

Steps:
1. ✅ Created TODO_RELATIONSHIPS.md
2. ✅ Edit Farms.php: belongsTo User ('owner_user_id'), hasMany Hogpens ('farm_id'), hasMany DailyFarmReports, Alerts
3. ✅ Edit Hogpens.php: belongsTo Farms ('farm_id'), hasMany Hogs, Feeders, FeedingSchedule, FeedingLogs, Sensors, Alerts, IotDevices (all 'pen_id')
4. ✅ Edit Hogs.php: belongsTo Hogpens ('pen_id'), hasMany HogDailyRecords, HogHealthPredictions ('hog_id')
5. ✅ Edit HogDailyRecords.php: belongsTo Hog ('hog_id'), Hogpens ('pen_id')
6. ✅ Edit Feeders.php: belongsTo Hogpens ('pen_id'), hasMany FeedingLogs
7. ✅ Edit FeedingSchedule.php: belongsTo Hogpens ('pen_id')
8. ✅ Edit FeedingLogs.php: belongsTo Feeders ('feeder_id'), Hogpens ('pen_id')
9. ✅ Edit Sensors.php: belongsTo Hogpens ('pen_id'), hasMany SensorReadings
10. ✅ Edit SensorReadings.php: belongsTo Sensors ('sensor_id')
11. ✅ Edit MLModels.php: hasMany HogHealthPredictions ('model_id'), FeedingPredictions
12. ✅ Edit HogHealthPredictions.php: belongsTo Hogs ('hog_id'), MLModels ('model_id')
13. ✅ Edit DailyFarmReports.php: belongsTo Farms ('farm_id')
14. ✅ Edit Alerts.php: belongsTo Farms ('farm_id'), Hogpens ('pen_id')
15. ✅ Edit IotDevices.php: belongsTo Hogpens ('pen_id'), hasMany DeviceLogs
16. ✅ Edit DeviceLogs.php: belongsTo IotDevices ('device_id')
17. ✅ Updated TODO
18. ✅ Test relations in tinker (e.g. php artisan tinker; $farm = App\\Models\\Farms::first(); $farm->hogpens()->get(); )

**Status:** All relationships added to relevant models. Models now fully connected for Eloquent queries (withEagerLoads, etc.). Ready for API usage.

Optional: Add FeedingPredictions.php if needed (visible in tabs).


Note: Used explicit FK keys where non-standard (e.g. 'pen_id'). Method names descriptive.

