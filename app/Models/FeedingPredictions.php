<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingPredictions extends Model
{
    protected $table = "feeding_predictions";

    protected $fillable = ['hog_pen_id','ml_model_id','predicted_feed_amount','confidence_score'];
}
