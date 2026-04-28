<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HogHealthPredictions extends Model
{
    protected $table = "hog_health_predictions";
    protected $fillable = ['hog_id', 'ml_model_id', 'predicted_status', 'risk_score'];

    public function hog()
    {
        return $this->belongsTo(Hogs::class, 'hog_id');
    }

    public function mlModel()
    {
        return $this->belongsTo(MLModels::class, 'model_id');
    }

    //
}
