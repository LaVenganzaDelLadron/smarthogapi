<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hogs extends Model
{

    protected $table = "hogs";
    protected $fillable = ['hog_pen_id', 'ear_tag_id', 'breed', 'gender', 'current_age', 'weight_current', 'health_status'];

    public function hogpen()
    {
        return $this->belongsTo(Hogpens::class, 'hog_pen_id');
    }

    public function hogDailyRecords()
    {
        return $this->hasMany(HogDailyRecords::class, 'hog_id');
    }

    public function hogHealthPredictions()
    {
        return $this->hasMany(HogHealthPredictions::class, 'hog_id');
    }

    //
}
